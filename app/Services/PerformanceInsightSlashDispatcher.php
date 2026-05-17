<?php

namespace App\Services;

use App\Mail\DailyPerformanceInsightMail;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Admin/root slash commands to generate the daily performance insight from the web assistant or WhatsApp.
 *
 * Supported (trimmed, case-insensitive):
 * - `/generar-insight` or `/generate-insight` — today, current team & user
 * - `/generar-insight --force` (aliases: `fuerza`, `force`, `-f`) — refresh even if one exists
 * - `/generar-insight 2026-05-17` — optional ISO date
 * - `/insight-diario`, `/daily-insight`, `/performance-insight` (or `/performance-insights`) — same behaviour
 */
class PerformanceInsightSlashDispatcher
{
    public function __construct(
        protected UserDailyPerformanceInsightService $insightService,
        protected UserDailyPerformanceInsightNotificationService $notificationService,
        protected AgentConversationContextService $contextService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function tryWebAssistantMessage(
        string $message,
        User $actor,
        int $teamId,
        User $contextUser,
        bool $hasAudio,
    ): ?array {
        if ($hasAudio || $actor->id !== $contextUser->id)
        {
            return null;
        }

        $trimmed = trim($message);
        if (! $this->isPerformanceInsightSlash($trimmed))
        {
            return null;
        }

        $parsed = $this->parseBody($trimmed);
        if ($parsed === null)
        {
            return [
                'success' => false,
                'message' => (string) __('app.assistant_slash_performance_insight_format'),
                '_http_status' => 422,
            ];
        }

        $team = $actor->currentTeam;
        if (! $team || (int) $team->id !== $teamId)
        {
            return [
                'success' => false,
                'message' => __('No team context.'),
                '_http_status' => 403,
            ];
        }

        $result = $this->generate($actor, $team, $parsed['force'], $parsed['date']);

        if (($result['success'] ?? false) !== true)
        {
            return array_merge($result, [
                '_http_status' => (int) ($result['_http_status'] ?? 422),
            ]);
        }

        $this->contextService->persistMessages(
            $actor->id,
            trim($message),
            (string) ($result['response'] ?? ''),
            null,
            [],
            [],
            [],
            [],
            $teamId,
            false,
            null,
        );

        $payload = [
            'success' => true,
            'response' => (string) ($result['response'] ?? ''),
            'action_performed' => 'performance_insight_generate',
            'insight_id' => $result['insight_id'] ?? null,
            'insight_date' => $result['insight_date'] ?? null,
            'notification_url' => $result['notification_url'] ?? null,
            'performance_insights_url' => $result['performance_insights_url'] ?? null,
            '_http_status' => 200,
        ];

        if ($hasAudio)
        {
            $payload['transcript'] = trim($message);
        }

        return $payload;
    }

    /**
     * @return array{whatsapp_reply: string}|null
     */
    public function tryWhatsAppInbound(string $body, User $contextUser, int $assistantTeamId): ?array
    {
        $trimmed = trim($body);
        if (! $this->isPerformanceInsightSlash($trimmed))
        {
            return null;
        }

        $parsed = $this->parseBody($trimmed);
        if ($parsed === null)
        {
            return [
                'whatsapp_reply' => (string) __('app.assistant_slash_performance_insight_format'),
            ];
        }

        if (! UserDailyPerformanceInsight::userEligibleForEvaluation($contextUser))
        {
            return [
                'whatsapp_reply' => (string) __('app.assistant_slash_performance_insight_forbidden'),
            ];
        }

        $team = Team::withoutGlobalScopes()->find($assistantTeamId);
        if (! $team || ! $contextUser->belongsToTeam($team))
        {
            return [
                'whatsapp_reply' => __('No team context.'),
            ];
        }

        $result = $this->generate($contextUser, $team, $parsed['force'], $parsed['date']);

        return [
            'whatsapp_reply' => (string) ($result['response'] ?? $result['message'] ?? __('No se pudo generar el insight.')),
        ];
    }

    public function isPerformanceInsightSlash(string $message): bool
    {
        $t = trim($message);

        return $t !== '' && preg_match(
            '#^/(?:generar-insight|generate-insight|insight-diario|daily-insight|performance-insights?)\b#iu',
            $t,
        ) === 1;
    }

    /**
     * @return array{force: bool, date: string|null}|null
     */
    public function parseBody(string $message): ?array
    {
        if (! $this->isPerformanceInsightSlash($message))
        {
            return null;
        }

        if (! preg_match(
            '#^/(?:generar-insight|generate-insight|insight-diario|daily-insight|performance-insights?)\b(.*)$#iu',
            $message,
            $matches,
        ))
        {
            return null;
        }

        $tail = trim((string) ($matches[1] ?? ''));
        $force = false;

        if (preg_match('/(?:^|\s)(?:--force|-f)(?:\s|$)/iu', $tail) === 1)
        {
            $force = true;
            $tail = trim((string) preg_replace('/(?:^|\s)(?:--force|-f)(?:\s|$)/iu', ' ', $tail));
        }

        if (preg_match('/\b(?:fuerza|force)\b/iu', $tail) === 1)
        {
            $force = true;
            $tail = trim((string) preg_replace('/\b(?:fuerza|force)\b/iu', ' ', $tail));
        }

        $date = null;
        if (preg_match('#\b(\d{4}-\d{2}-\d{2})\b#', $tail, $dateMatch))
        {
            $date = $dateMatch[1];
            $tail = trim(str_replace($dateMatch[0], '', $tail));
        }

        if ($tail !== '')
        {
            return null;
        }

        return [
            'force' => $force,
            'date' => $date,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(User $user, Team $team, bool $force = false, ?string $dateString = null): array
    {
        if (! UserDailyPerformanceInsight::userEligibleForEvaluation($user))
        {
            return [
                'success' => false,
                'message' => (string) __('app.assistant_slash_performance_insight_forbidden'),
                '_http_status' => 403,
            ];
        }

        if (! $team->hasModule('performance_insights'))
        {
            return [
                'success' => false,
                'message' => (string) __('app.assistant_slash_performance_insight_no_module'),
                '_http_status' => 422,
            ];
        }

        $date = $dateString !== null && $dateString !== ''
            ? Carbon::parse($dateString)
            : now();

        $insightDate = $date->toDateString();
        $hadInsight = $this->notificationService->insightExistsForDay($user, $team, $insightDate);

        $insight = $this->insightService->ensureTodayRecord($user, $team, null, $date, $force);

        $notification = null;
        if ($team->performanceInsightsInAppNotificationEnabled())
        {
            $notification = $this->notificationService->syncForInsight($insight, $team, markUnread: $force || ! $hadInsight);
        }

        if (config('daily_performance_insight.send_email', true) && $user->email)
        {
            Mail::to($user->email)->send(new DailyPerformanceInsightMail($insight));
        }

        $performanceInsightsUrl = route('performance-insights.index', ['insight_date' => $insightDate]);
        $notificationUrl = $this->resolveNotificationUrl($notification, $insight->id, $team->id);

        $refreshed = $force || ! $hadInsight;

        return [
            'success' => true,
            'response' => (string) __('app.assistant_slash_performance_insight_success', [
                'date' => $insightDate,
                'headline' => $insight->headline,
                'ratio' => number_format((float) $insight->performance_ratio, 2),
                'refreshed' => $refreshed ? __('app.assistant_slash_performance_insight_refreshed') : __('app.assistant_slash_performance_insight_kept'),
                'insights_url' => $performanceInsightsUrl,
                'notification_url' => $notificationUrl ?? $performanceInsightsUrl,
            ]),
            'insight_id' => $insight->id,
            'insight_date' => $insightDate,
            'notification_url' => $notificationUrl,
            'performance_insights_url' => $performanceInsightsUrl,
            '_http_status' => 200,
        ];
    }

    private function resolveNotificationUrl(?Notification $notification, int $insightId, int $teamId): ?string
    {
        if ($notification !== null && \Illuminate\Support\Facades\Route::has('notification.show'))
        {
            return route('notification.show', $notification->id);
        }

        if ($insightId <= 0)
        {
            return null;
        }

        $fallback = Notification::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('reference', $insightId)
            ->orderByDesc('id')
            ->first();

        if ($fallback !== null && \Illuminate\Support\Facades\Route::has('notification.show'))
        {
            return route('notification.show', $fallback->id);
        }

        return null;
    }
}
