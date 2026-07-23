<?php

namespace App\Services;

use App\Mail\AutomationFunnelCompletedMail;
use App\Models\Automation;
use App\Models\AutomationFlowSession;
use App\Models\AutomationStep;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AutomationFunnelCompletionNotifier
{
    public function __construct(
        protected AgentConversationContextService $conversationContext,
    ) {}

    /**
     * Notify the completer when a funnel reaches a terminal step or explicit completion.
     *
     * @param  list<array{role?: string, content?: string, direction?: string, body?: string}>|null  $conversationExcerpts
     */
    public function notifyIfEligible(
        Automation $automation,
        ?AutomationFlowSession $session,
        ?AutomationStep $step = null,
        bool $flowCompleted = false,
        ?array $conversationExcerpts = null,
    ): bool {
        if (! $automation->isFunnel())
        {
            return false;
        }

        if (! $flowCompleted && ! $this->isTerminalStep($step))
        {
            return false;
        }

        if ($session !== null && data_get($session->meta, 'completion_email_sent_at'))
        {
            return false;
        }

        $recipient = $this->resolveRecipient($session, $automation);
        if ($recipient === null || trim((string) ($recipient['email'] ?? '')) === '')
        {
            Log::info('AutomationFunnelCompletionNotifier: no recipient email', [
                'automation_id' => $automation->id,
                'session_id' => $session?->id,
            ]);

            return false;
        }

        $excerpts = $conversationExcerpts ?? $this->conversationExcerptsForRecipient($recipient, (int) $automation->team_id);
        $summaryLines = $this->summaryLines($automation, $excerpts);

        $automation->loadMissing(['steps', 'team']);

        try
        {
            Mail::to($recipient['email'])->send(new AutomationFunnelCompletedMail(
                automation: $automation,
                recipientName: $recipient['name'] ?: __('usuario'),
                summaryLines: $summaryLines,
                conversationExcerpts: $excerpts,
                teamName: $automation->team?->name,
            ));
        } catch (Throwable $e)
        {
            Log::warning('AutomationFunnelCompletionNotifier: failed to send', [
                'automation_id' => $automation->id,
                'email' => $recipient['email'],
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($session !== null)
        {
            $meta = is_array($session->meta) ? $session->meta : [];
            $meta['completion_email_sent_at'] = now()->toIso8601String();
            $meta['completion_email_to'] = $recipient['email'];
            $session->meta = $meta;
            $session->save();
        }

        return true;
    }

    public function isTerminalStep(?AutomationStep $step): bool
    {
        if ($step === null)
        {
            return false;
        }

        $step->loadMissing('transitions');

        return $step->transitions->isEmpty();
    }

    /**
     * @return array{email: string, name: string, user_id: int|null}|null
     */
    public function resolveRecipient(?AutomationFlowSession $session, Automation $automation): ?array
    {
        $metaEmail = trim((string) data_get($session?->meta, 'recipient_email', ''));
        if (filter_var($metaEmail, FILTER_VALIDATE_EMAIL))
        {
            return [
                'email' => $metaEmail,
                'name' => trim((string) data_get($session?->meta, 'recipient_name', '')) ?: $metaEmail,
                'user_id' => null,
            ];
        }

        $externalKey = trim((string) ($session?->external_key ?? ''));
        if (preg_match('/^user:(\d+)$/', $externalKey, $matches) === 1)
        {
            $user = User::query()->find((int) $matches[1]);
            if ($user && filter_var($user->email, FILTER_VALIDATE_EMAIL))
            {
                return [
                    'email' => $user->email,
                    'name' => $user->name ?: $user->email,
                    'user_id' => $user->id,
                ];
            }
        }

        if (auth()->check() && filter_var(auth()->user()->email, FILTER_VALIDATE_EMAIL))
        {
            $user = auth()->user();

            return [
                'email' => $user->email,
                'name' => $user->name ?: $user->email,
                'user_id' => $user->id,
            ];
        }

        return null;
    }

    /**
     * @param  array{email: string, name: string, user_id: int|null}  $recipient
     * @return list<array{role: string, content: string}>
     */
    protected function conversationExcerptsForRecipient(array $recipient, int $teamId): array
    {
        $userId = $recipient['user_id'] ?? null;
        if ($userId === null)
        {
            return [];
        }

        return collect($this->conversationContext->getMessagesForDisplay((int) $userId, 40, $teamId))
            ->map(fn (array $message): array => [
                'role' => (string) ($message['role'] ?? 'assistant'),
                'content' => trim((string) ($message['content'] ?? '')),
            ])
            ->filter(fn (array $message): bool => $message['content'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{role: string, content: string}>  $excerpts
     * @return list<string>
     */
    protected function summaryLines(Automation $automation, array $excerpts): array
    {
        $lines = [];
        $automation->loadMissing('steps');

        foreach ($automation->steps as $step)
        {
            $label = trim((string) $step->label);
            if ($label !== '')
            {
                $lines[] = $label;
            }
        }

        $userAnswers = collect($excerpts)
            ->where('role', 'user')
            ->pluck('content')
            ->filter(fn (string $content): bool => $content !== '' && ! preg_match('/^[a-z0-9][a-z0-9\-_]*$/', strtolower($content)))
            ->take(8)
            ->values()
            ->all();

        if ($userAnswers !== [])
        {
            $lines[] = __('Respuestas destacadas:');
            foreach ($userAnswers as $answer)
            {
                $lines[] = '• '.$this->truncate($answer, 220);
            }
        }

        return $lines;
    }

    protected function truncate(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $limit)
        {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1).'…';
    }
}
