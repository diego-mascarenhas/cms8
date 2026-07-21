<?php

namespace App\Services;

use App\Enums\AutomationReplyType;
use App\Models\Automation;
use App\Models\AutomationFlowSession;
use App\Models\AutomationStep;
use App\Models\AutomationTransition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AutomationFlowEngine
{
    /**
     * Get or create a flow session for this automation + channel + external key.
     */
    public function sessionFor(Automation $automation, string $channel, string $externalKey): AutomationFlowSession
    {
        $externalKey = trim($externalKey) !== '' ? trim($externalKey) : 'anonymous';

        $session = AutomationFlowSession::query()->firstOrCreate(
            [
                'automation_id' => $automation->id,
                'channel' => $channel,
                'external_key' => $externalKey,
            ],
            [
                'team_id' => $automation->team_id,
                'current_step_id' => $automation->entryStep()?->id,
                'meta' => ['awaiting_reply' => false],
                'last_message_at' => now(),
            ],
        );

        if ($session->current_step_id === null)
        {
            $entry = $automation->entryStep();
            if ($entry)
            {
                $session->current_step_id = $entry->id;
                $session->save();
            }
        }

        return $session->fresh(['currentStep']);
    }

    public function resetSession(AutomationFlowSession $session): AutomationFlowSession
    {
        $entry = $session->automation?->entryStep()
            ?? Automation::query()->find($session->automation_id)?->entryStep();
        $session->current_step_id = $entry?->id;
        $session->meta = ['awaiting_reply' => false];
        $session->last_message_at = now();
        $session->save();

        return $session->fresh(['currentStep']);
    }

    /**
     * Resolve which step should handle this user message (may advance the funnel).
     *
     * @return array{
     *     step: AutomationStep|null,
     *     matched_transition: AutomationTransition|null,
     *     completed: bool,
     *     exit_automation_id: int|null,
     *     session: AutomationFlowSession
     * }
     */
    public function resolveStepForMessage(AutomationFlowSession $session, string $userMessage): array
    {
        $session->loadMissing(['currentStep.transitions', 'automation']);
        $automation = $session->automation;

        if (! $automation || ! $automation->hasFlowGraph())
        {
            return [
                'step' => null,
                'matched_transition' => null,
                'completed' => false,
                'exit_automation_id' => null,
                'session' => $session,
            ];
        }

        $current = $session->currentStep;
        if ($current === null)
        {
            $current = $automation->entryStep();
            $session->current_step_id = $current?->id;
            $session->last_message_at = now();
            $session->save();

            return [
                'step' => $current,
                'matched_transition' => null,
                'completed' => false,
                'exit_automation_id' => null,
                'session' => $session->fresh(['currentStep']),
            ];
        }

        $awaiting = (bool) data_get($session->meta, 'awaiting_reply', false);
        if (! $awaiting)
        {
            $session->last_message_at = now();
            $session->save();

            return [
                'step' => $current,
                'matched_transition' => null,
                'completed' => false,
                'exit_automation_id' => null,
                'session' => $session,
            ];
        }

        $transition = $this->matchTransition($current, $userMessage);
        if ($transition === null)
        {
            return [
                'step' => $current,
                'matched_transition' => null,
                'completed' => false,
                'exit_automation_id' => null,
                'session' => $session,
            ];
        }

        $exitAutomationId = $transition->to_automation_id ? (int) $transition->to_automation_id : null;
        $next = null;
        if ($exitAutomationId === null && $transition->to_step_id)
        {
            $next = AutomationStep::query()->find($transition->to_step_id);
        }

        $session->current_step_id = $next?->id;
        $session->last_message_at = now();
        $meta = is_array($session->meta) ? $session->meta : [];
        $meta['awaiting_reply'] = false;
        $meta['last_reply_type'] = $transition->reply_type->value;
        $meta['last_match'] = $transition->match_value;
        if ($exitAutomationId !== null)
        {
            $meta['last_exit_automation_id'] = $exitAutomationId;
        }
        $session->meta = $meta;
        $session->save();

        return [
            'step' => $next,
            'matched_transition' => $transition,
            'completed' => $next === null && $exitAutomationId === null,
            'exit_automation_id' => $exitAutomationId,
            'session' => $session->fresh(['currentStep']),
        ];
    }

    public function markAwaitingReply(AutomationFlowSession $session): void
    {
        $meta = is_array($session->meta) ? $session->meta : [];
        $meta['awaiting_reply'] = true;
        $session->meta = $meta;
        $session->last_message_at = now();
        $session->save();
    }

    public function matchTransition(AutomationStep $step, string $userMessage): ?AutomationTransition
    {
        $transitions = $step->relationLoaded('transitions')
            ? $step->transitions->sortBy('sort_order')->values()
            : $step->transitions()->orderBy('sort_order')->get();

        $fallback = null;

        foreach ($transitions as $transition)
        {
            if ($transition->reply_type === AutomationReplyType::Fallback)
            {
                $fallback = $transition;

                continue;
            }

            if ($this->matches($transition, $userMessage))
            {
                return $transition;
            }
        }

        return $fallback;
    }

    public function matches(AutomationTransition $transition, string $userMessage): bool
    {
        $text = trim($userMessage);
        $normalized = Str::lower($text);

        return match ($transition->reply_type)
        {
            AutomationReplyType::YesNo => $this->matchesYesNo($normalized, $transition->match_value),
            AutomationReplyType::Choice => $this->matchesChoice($normalized, $transition->match_value, $transition->label),
            AutomationReplyType::Email => (bool) filter_var($text, FILTER_VALIDATE_EMAIL),
            AutomationReplyType::Phone => (bool) preg_match('/^\+?[\d\s().-]{7,20}$/', $text),
            AutomationReplyType::Date => $this->matchesDate($text),
            AutomationReplyType::FreeText => $text !== '',
            AutomationReplyType::Fallback => true,
        };
    }

    protected function matchesYesNo(string $normalized, ?string $expected): bool
    {
        $yes = ['si', 'sí', 'yes', 'y', 'ok', 'vale', 'claro', 'dale', 'confirmo'];
        $no = ['no', 'nop', 'nope', 'cancel', 'cancelar', 'nah'];

        $isYes = collect($yes)->contains(
            fn (string $word) => $normalized === $word || str_starts_with($normalized, $word.' '),
        );
        $isNo = collect($no)->contains(
            fn (string $word) => $normalized === $word || str_starts_with($normalized, $word.' '),
        );

        $want = Str::lower(trim((string) $expected));
        if (in_array($want, ['yes', 'si', 'sí', 'true', '1'], true))
        {
            return $isYes;
        }
        if (in_array($want, ['no', 'false', '0'], true))
        {
            return $isNo;
        }

        return $isYes || $isNo;
    }

    protected function matchesChoice(string $normalized, ?string $matchValue, ?string $label): bool
    {
        $candidates = array_filter([
            $matchValue !== null ? Str::lower(trim($matchValue)) : null,
            $label !== null ? Str::lower(trim($label)) : null,
        ]);

        foreach ($candidates as $candidate)
        {
            if ($candidate !== '' && ($normalized === $candidate || str_contains($normalized, $candidate)))
            {
                return true;
            }
        }

        return false;
    }

    protected function matchesDate(string $text): bool
    {
        try
        {
            Carbon::parse($text);

            return true;
        } catch (\Throwable)
        {
            return (bool) preg_match('/\b\d{1,2}[\/\-]\d{1,2}([\/\-]\d{2,4})?\b/', $text);
        }
    }

    public function stepSystemAppendix(AutomationStep $step): string
    {
        $lines = [];
        $instruction = trim((string) ($step->instruction ?? ''));
        if ($instruction !== '')
        {
            $lines[] = '# Paso del embudo: '.$step->label;
            $lines[] = $instruction;
        }

        $transitions = $step->relationLoaded('transitions')
            ? $step->transitions->sortBy('sort_order')
            : $step->transitions()->orderBy('sort_order')->get();

        if ($transitions->isNotEmpty())
        {
            $lines[] = '';
            $lines[] = '## Respuestas esperadas del usuario';
            foreach ($transitions as $t)
            {
                $label = $t->label ?: $t->reply_type->label();
                $extra = $t->match_value ? ' (match: '.$t->match_value.')' : '';
                $exit = $t->to_automation_id
                    ? ' → automatización #'.$t->to_automation_id
                    : '';
                $lines[] = '- '.$label.': tipo `'.$t->reply_type->value.'`'.$extra.$exit;
            }
            $lines[] = 'Guiá la conversación para obtener una de esas respuestas. Si responde otra cosa, pedí aclaración o usá la rama fallback si existe.';
        }

        return implode("\n", $lines);
    }
}
