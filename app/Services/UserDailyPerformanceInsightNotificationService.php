<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;

class UserDailyPerformanceInsightNotificationService
{
    public const TYPE_NAME = 'Daily Performance Insight';

    /**
     * Create or refresh the in-app notification tied to a daily performance insight.
     */
    public function syncForInsight(UserDailyPerformanceInsight $insight, Team $team, bool $markUnread = true): ?Notification
    {
        if (! $team->performanceInsightsInAppNotificationEnabled())
        {
            return null;
        }

        $recipient = $insight->user ?? User::query()->find($insight->user_id);
        if (! $recipient)
        {
            return null;
        }

        $contact = $this->resolveRecipientContact($recipient, $team);
        if (! $contact)
        {
            return null;
        }

        $typeId = $this->resolveTypeId();
        $senderId = (int) ($team->user_id ?: $recipient->id);
        $existing = Notification::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('type_id', $typeId)
            ->where('reference', $insight->id)
            ->first();

        $preserveReadState = $existing?->is_read === true && ! $markUnread;

        $insightDate = $insight->insight_date?->toDateString() ?? now()->toDateString();

        return Notification::withoutGlobalScopes()->updateOrCreate(
            [
                'team_id' => $team->id,
                'type_id' => $typeId,
                'reference' => $insight->id,
            ],
            [
                'contact_id' => $contact->id,
                'user_id' => $senderId,
                'subject' => (string) $insight->headline,
                'message' => $this->buildMessage($insight),
                'is_sent' => true,
                'sent_at' => now(),
                'sent_data' => [
                    'channel' => 'in_app',
                    'source' => 'performance_insights',
                ],
                'is_read' => $preserveReadState,
                'read_at' => $preserveReadState ? $existing?->read_at : null,
                'metadata' => [
                    'performance_insight_id' => $insight->id,
                    'insight_date' => $insightDate,
                    'performance_ratio' => (float) $insight->performance_ratio,
                    'action_url' => route('performance-insights.index', ['insight_date' => $insightDate]),
                ],
            ],
        );
    }

    private function buildMessage(UserDailyPerformanceInsight $insight): string
    {
        $focus = trim(strip_tags((string) $insight->focus));
        $body = trim(strip_tags((string) $insight->message));
        $ratioLine = __('app.performance_insight_notification_ratio', [
            'ratio' => number_format((float) $insight->performance_ratio, 2),
        ]);

        $parts = array_filter([$focus, $body, $ratioLine]);

        return implode("\n\n", $parts);
    }

    private function resolveTypeId(): int
    {
        $type = NotificationType::query()->firstOrCreate(
            ['name' => self::TYPE_NAME],
            [
                'template_subject' => __('app.performance_insight_notification_subject', ['headline' => '{headline}']),
                'template_body' => '{focus}'."\n\n".'{message}',
                'is_customizable' => false,
                'is_active' => true,
            ],
        );

        return (int) $type->id;
    }

    private function resolveRecipientContact(User $user, Team $team): ?Contact
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $contact && $user->email)
        {
            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('email', $user->email)
                ->first();

            if ($contact && (int) $contact->user_id !== (int) $user->id)
            {
                $contact->forceFill(['user_id' => $user->id])->save();
            }
        }

        if ($contact)
        {
            return $contact;
        }

        if (! $user->email)
        {
            return null;
        }

        [$name, $surname] = $this->splitUserName($user->name);
        $ownerId = (int) ($team->user_id ?: $user->id);

        return Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'email' => $user->email,
            'name' => $name,
            'surname' => $surname,
            'phone' => (string) ($user->phone ?? '34600000000'),
            'creator_id' => $ownerId,
            'responsible_id' => $ownerId,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'engagment' => 'temperate',
            'user_id' => $user->id,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitUserName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [
            $parts[0] ?? $fullName,
            $parts[1] ?? '',
        ];
    }

    public function insightExistsForDay(User $user, Team $team, string $date): bool
    {
        return UserDailyPerformanceInsight::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereDate('insight_date', $date)
            ->exists();
    }
}
