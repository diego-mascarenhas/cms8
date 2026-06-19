<?php

namespace App\Services;

use App\Jobs\SendScheduledMessageJob;
use App\Models\Notification;
use App\Models\ScheduledMessage;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PerformanceDigestScheduleReplyService
{
    public const DELAY_HOURS = 2;

    public function schedule(
        Team $team,
        User $user,
        string $channel,
        string $recipient,
        string $body,
        ?string $subject = null,
        ?int $notificationId = null,
        ?string $highlightKey = null,
        ?int $digestMessageId = null,
    ): ScheduledMessage {
        if ($notificationId !== null && $highlightKey !== null && $highlightKey !== '')
        {
            $this->cancelExistingPending($team->id, $notificationId, $highlightKey, $digestMessageId);
        }

        $scheduledAt = now()->addHours(self::DELAY_HOURS);

        $metadata = [
            'source' => 'performance_digest',
        ];

        if ($notificationId !== null)
        {
            $metadata['notification_id'] = $notificationId;
        }

        if ($highlightKey !== null && $highlightKey !== '')
        {
            $metadata['highlight_key'] = $highlightKey;
        }

        if ($digestMessageId !== null && $digestMessageId > 0)
        {
            $metadata['digest_message_id'] = $digestMessageId;
        }

        if ($channel === 'email' && $subject !== null && trim($subject) !== '')
        {
            $metadata['subject'] = trim($subject);
        }

        $scheduled = ScheduledMessage::create([
            'team_id' => $team->id,
            'scheduled_by_user_id' => $user->id,
            'recipient' => $channel === 'whatsapp'
                ? (preg_replace('/\D/', '', $recipient) ?? $recipient)
                : trim($recipient),
            'channel' => $channel,
            'body' => trim($body),
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        SendScheduledMessageJob::dispatch($scheduled->id)->delay($scheduledAt);

        return $scheduled;
    }

    /**
     * @param  list<array<string, mixed>>  $highlights
     * @return list<array<string, mixed>>
     */
    public function attachToHighlights(array $highlights, Notification $notification): array
    {
        $pending = $this->pendingForNotification($notification);

        if ($pending->isEmpty())
        {
            return $highlights;
        }

        $byMessageKey = [];
        $byHighlightRecipient = [];

        foreach ($pending as $scheduled)
        {
            $meta = $scheduled->metadata ?? [];
            $highlightKey = (string) ($meta['highlight_key'] ?? '');

            if ($highlightKey === '')
            {
                continue;
            }

            $messageId = (int) ($meta['digest_message_id'] ?? 0);
            $byMessageKey[$this->lookupKey($highlightKey, $messageId)] = $scheduled;

            $recipientKey = $highlightKey.':'.$scheduled->channel.':'.$this->normalizeRecipientForLookup($scheduled->channel, $scheduled->recipient);
            $byHighlightRecipient[$recipientKey] = $scheduled;
        }

        $enriched = [];

        foreach ($highlights as $highlight)
        {
            $highlightKey = (string) ($highlight['key'] ?? '');

            if (! empty($highlight['messages']) && is_array($highlight['messages']))
            {
                $messages = [];

                foreach ($highlight['messages'] as $message)
                {
                    $messages[] = $this->attachScheduledStateToContext(
                        $message,
                        $highlightKey,
                        $byMessageKey,
                        $byHighlightRecipient,
                    );
                }

                $highlight['messages'] = $messages;
            } elseif (($highlight['schedule_action'] ?? null) !== null)
            {
                $highlight = $this->attachScheduledStateToContext(
                    $highlight,
                    $highlightKey,
                    $byMessageKey,
                    $byHighlightRecipient,
                    useHighlightRecipientFallback: true,
                );
            }

            $enriched[] = $highlight;
        }

        return $enriched;
    }

    public function cancel(ScheduledMessage $scheduled): void
    {
        if ($scheduled->isPending())
        {
            $scheduled->markAsCancelled();
        }
    }

    public function belongsToDigestNotification(ScheduledMessage $scheduled, Notification $notification): bool
    {
        if ((int) $scheduled->team_id !== (int) $notification->team_id)
        {
            return false;
        }

        $meta = $scheduled->metadata ?? [];

        return ($meta['source'] ?? '') === 'performance_digest'
            && (int) ($meta['notification_id'] ?? 0) === (int) $notification->id;
    }

    public function formattedScheduleLabel(CarbonInterface $scheduledAt): string
    {
        return $scheduledAt->format('d/m/Y H:i');
    }

    /**
     * @return Collection<int, ScheduledMessage>
     */
    private function pendingForNotification(Notification $notification): Collection
    {
        return ScheduledMessage::query()
            ->where('team_id', $notification->team_id)
            ->where('status', 'pending')
            ->where('metadata->source', 'performance_digest')
            ->where('metadata->notification_id', $notification->id)
            ->orderBy('scheduled_at')
            ->get();
    }

    private function cancelExistingPending(int $teamId, int $notificationId, string $highlightKey, ?int $digestMessageId): void
    {
        $query = ScheduledMessage::query()
            ->where('team_id', $teamId)
            ->where('status', 'pending')
            ->where('metadata->source', 'performance_digest')
            ->where('metadata->notification_id', $notificationId)
            ->where('metadata->highlight_key', $highlightKey);

        if ($digestMessageId !== null && $digestMessageId > 0)
        {
            $query->where('metadata->digest_message_id', $digestMessageId);
        }

        $query->get()->each(fn (ScheduledMessage $scheduled) => $scheduled->markAsCancelled());
    }

    /**
     * @param  array<string, ScheduledMessage>  $byMessageKey
     * @param  array<string, ScheduledMessage>  $byHighlightRecipient
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function attachScheduledStateToContext(
        array $context,
        string $highlightKey,
        array $byMessageKey,
        array $byHighlightRecipient,
        bool $useHighlightRecipientFallback = false,
    ): array {
        $messageId = (int) ($context['id'] ?? 0);
        $scheduled = $byMessageKey[$this->lookupKey($highlightKey, $messageId)] ?? null;

        if ($scheduled === null && $useHighlightRecipientFallback)
        {
            $scheduleAction = (string) ($context['schedule_action'] ?? '');
            $scheduleRecipient = (string) ($context['schedule_recipient'] ?? '');

            if ($scheduleAction !== '' && $scheduleRecipient !== '')
            {
                $recipientKey = $highlightKey.':'.$scheduleAction.':'.$this->normalizeRecipientForLookup($scheduleAction, $scheduleRecipient);
                $scheduled = $byHighlightRecipient[$recipientKey] ?? $byMessageKey[$this->lookupKey($highlightKey, 0)] ?? null;
            }
        }

        if ($scheduled === null && $messageId > 0)
        {
            $scheduleAction = (string) ($context['schedule_action'] ?? '');
            $scheduleRecipient = (string) ($context['schedule_recipient'] ?? '');

            if ($scheduleAction !== '' && $scheduleRecipient !== '')
            {
                $recipientKey = $highlightKey.':'.$scheduleAction.':'.$this->normalizeRecipientForLookup($scheduleAction, $scheduleRecipient);
                $scheduled = $byHighlightRecipient[$recipientKey] ?? null;
            }
        }

        if ($scheduled === null)
        {
            return $context;
        }

        $context['scheduled_message_id'] = $scheduled->id;
        $context['scheduled_at'] = $scheduled->scheduled_at?->toIso8601String();
        $context['scheduled_label'] = $scheduled->scheduled_at !== null
            ? $this->formattedScheduleLabel($scheduled->scheduled_at)
            : null;
        $context['schedule_action'] = null;
        $context['action_url'] = null;
        $context['action_label'] = null;

        return $context;
    }

    private function lookupKey(string $highlightKey, int $digestMessageId): string
    {
        return $highlightKey.':'.$digestMessageId;
    }

    private function normalizeRecipientForLookup(string $channel, string $recipient): string
    {
        if ($channel === 'whatsapp')
        {
            return preg_replace('/\D/', '', $recipient) ?? $recipient;
        }

        return strtolower(trim($recipient));
    }
}
