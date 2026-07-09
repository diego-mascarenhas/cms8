<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use App\Support\AiTasks;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class UserDailyPerformanceInsightService
{
    public function __construct(
        private readonly DailyTeamDigestMetricsCollector $digestCollector,
    ) {}

    private const LLM_INSTRUCTIONS = <<<'PROMPT'
You write a daily team operations digest notification for one admin user.
The context JSON includes team_digest (WhatsApp, email, appointments, client sentiment, tasks, projects, services, invoices, payments when present) and digest_highlights (priority bullets).
Reply with ONLY a JSON object (no markdown code fences, no extra text). Keys:
- "headline": exactly one word in the requested output language, optionally immediately followed by one emoji (no space) that fits the tone. Sharp stance (noun, imperative verb, or adjective). Never greetings or empty praise. Forbidden examples for the word part (any language): congratulations, bravo, hola, hello, welcome, felicidades, bienvenido, awesome, perfect, great job.
- "focus": exactly five words (single spaces), same language, the single priority to tackle today from digest_highlights and team_digest. Start with a capital letter. You may use at most one line break inside "focus" between words; total word count remains five across both lines.
- "message": exactly one short, upbeat paragraph in the same language (no bullets, no markdown). Aim for roughly 28–45 words: warm, actionable, never preachy. Mention the most urgent signals from digest_highlights (unread WhatsApp, overdue invoices, stressed clients, overdue tasks, appointments today, etc.) without listing every number. If mentoring_phase is set, mention it once. End with one concrete next step aligned with "focus". No ALL CAPS.
Use letters (unicode) and digits inside words; "headline" has no spaces between word and optional emoji.
PROMPT;

    /** @var list<string> */
    private const FORBIDDEN_HEADLINE_TOKENS = [
        'congratulations', 'congrats', 'bravo', 'hola', 'hello', 'hi', 'welcome', 'felicidades',
        'bienvenido', 'bienvenida', 'awesome', 'perfect', 'perfecto', 'great', 'genial', 'outstanding',
        'congratulaciones', 'enhorabuena', 'felicitaciones',
    ];

    /**
     * Return the persisted insight for the given calendar day. Unless $forceRegenerate
     * is true, an existing row for that day is returned as-is (no recalculation).
     *
     * @param  bool  $forceRegenerate  When true, recalculates metrics and overwrites headline, focus, message, ratio and snapshot.
     */
    public function ensureTodayRecord(User $user, Team $team, ?string $mentoringHeadline, ?CarbonInterface $onDate = null, bool $forceRegenerate = false, ?string $outputLocale = null): UserDailyPerformanceInsight
    {
        if (! UserDailyPerformanceInsight::userEligibleForEvaluation($user))
        {
            throw new \InvalidArgumentException('Daily performance insights are only generated for users with the admin role.');
        }

        $date = ($onDate ?? now())->toDateString();

        if (! $forceRegenerate)
        {
            $existing = UserDailyPerformanceInsight::query()
                ->where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->whereDate('insight_date', $date)
                ->first();

            if ($existing && ! $this->storedInsightNeedsMicroUpgrade($existing))
            {
                return $existing;
            }
        }

        $locale = $outputLocale ?? app()->getLocale();
        $digest = $this->digestCollector->collect($user, $team, $onDate);
        $metrics = $digest['user_activity'];
        $ratio = $this->computePerformanceRatio($digest);

        $insight = $this->resolveInsightPayload($user, $team, $digest, $ratio, $mentoringHeadline, $locale);

        $snapshot = array_merge($digest, array_filter([
            'mentoring_phase_label' => $mentoringHeadline,
            'insight_source' => $insight['source'],
            'output_locale' => $locale,
        ], fn ($v) => $v !== null && $v !== ''));

        $payload = [
            'performance_ratio' => $ratio,
            'headline' => $insight['headline'],
            'focus' => $insight['focus'],
            'message' => $insight['message'],
            'context_snapshot' => $snapshot,
        ];

        $row = UserDailyPerformanceInsight::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereDate('insight_date', $date)
            ->first();

        if ($row)
        {
            $row->update($payload);

            return $row->refresh();
        }

        return UserDailyPerformanceInsight::query()->create(array_merge(
            [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'insight_date' => $date,
            ],
            $payload,
        ));
    }

    /**
     * Existing persisted insight for the calendar day, if any. Does not create rows (use {@see ensureTodayRecord} from jobs/commands).
     */
    public function findTodayInsight(User $user, Team $team, ?CarbonInterface $onDate = null): ?UserDailyPerformanceInsight
    {
        $date = ($onDate ?? now())->toDateString();

        return UserDailyPerformanceInsight::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereDate('insight_date', $date)
            ->first();
    }

    /**
     * @return array{interactions_count: int, call_minutes: float, tasks_done: int}
     */
    public function collectMetrics(User $user, Team $team): array
    {
        return $this->digestCollector->collect($user, $team)['user_activity'];
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    public function computePerformanceRatio(array $digest): float
    {
        $activity = $digest['user_activity'] ?? [];
        $interactions = (int) ($activity['interactions_count'] ?? 0);
        $minutes = (float) ($activity['call_minutes'] ?? 0);
        $tasks = (int) ($activity['tasks_done'] ?? 0);

        $score = min(40, $interactions * 4) + min(35, ($minutes / 60) * 10) + min(25, $tasks * 8);

        if (isset($digest['whatsapp']))
        {
            $score += min(10, (int) ($digest['whatsapp']['inbound_7d'] ?? 0));
            $score += min(5, (int) ($digest['whatsapp']['unread_inbound'] ?? 0) * 2);
        }

        if (isset($digest['tasks']))
        {
            $score -= min(15, (int) ($digest['tasks']['overdue'] ?? 0) * 3);
        }

        if (isset($digest['invoices']))
        {
            $score -= min(10, (int) ($digest['invoices']['overdue_count'] ?? 0) * 2);
        }

        return round(max(0, min(100, $score)), 2);
    }

    public function buildMessage(array $metrics, float $ratio): string
    {
        $activity = $metrics['user_activity'] ?? $metrics;
        $replacements = [
            'interactions' => (int) ($activity['interactions_count'] ?? 0),
            'minutes' => (int) round((float) ($activity['call_minutes'] ?? 0)),
            'tasks' => (int) ($activity['tasks_done'] ?? 0),
        ];

        if ($ratio >= 70.0)
        {
            return (string) __('app.performance_insight_message_tier_high', $replacements);
        }

        if ($ratio >= 40.0)
        {
            return (string) __('app.performance_insight_message_tier_mid', $replacements);
        }

        return (string) __('app.performance_insight_message_tier_low', $replacements);
    }

    /**
     * Legacy long-form title for admin listings; dashboard uses LLM or emergency micro-copy.
     */
    public function buildDailyHeadline(array $metrics, float $ratio): string
    {
        $activity = $metrics['user_activity'] ?? $metrics;
        $replacements = [
            'interactions' => (int) ($activity['interactions_count'] ?? 0),
            'minutes' => (int) round((float) ($activity['call_minutes'] ?? 0)),
            'tasks' => (int) ($activity['tasks_done'] ?? 0),
        ];

        if ($ratio >= 70.0)
        {
            return (string) __('app.performance_insight_headline_tier_high', $replacements);
        }

        if ($ratio >= 40.0)
        {
            return (string) __('app.performance_insight_headline_tier_mid', $replacements);
        }

        return (string) __('app.performance_insight_headline_tier_low', $replacements);
    }

    /**
     * @return array{headline: string, focus: string, message: string, source: string}
     */
    private function resolveInsightPayload(User $user, Team $team, array $digest, float $ratio, ?string $mentoringHeadline, string $locale): array
    {
        if (config('daily_performance_insight.use_llm', true))
        {
            $fromLlm = $this->tryBuildInsightWithLlm($user, $team, $digest, $ratio, $mentoringHeadline, $locale);
            if ($fromLlm !== null)
            {
                return $fromLlm;
            }
        }

        return $this->buildEmergencyInsight($ratio, $locale, $digest, $mentoringHeadline);
    }

    /**
     * @return ?array{headline: string, focus: string, message: string, source: 'llm'}
     */
    private function tryBuildInsightWithLlm(User $user, Team $team, array $digest, float $ratio, ?string $mentoringHeadline, string $locale): ?array
    {
        try
        {
            $activity = $digest['user_activity'] ?? [];
            $context = [
                'user_first_name' => preg_split('/\s+/u', trim($user->name), 2, PREG_SPLIT_NO_EMPTY)[0] ?? '',
                'user_full_name' => $user->name,
                'team_name' => $team->name,
                'mentoring_phase' => $mentoringHeadline,
                'rolling_7d_interactions' => $activity['interactions_count'] ?? 0,
                'rolling_7d_call_minutes' => round((float) ($activity['call_minutes'] ?? 0), 1),
                'rolling_7d_tasks_done' => $activity['tasks_done'] ?? 0,
                'performance_ratio_0_100' => $ratio,
                'team_digest' => array_diff_key($digest, array_flip(['highlights', 'user_activity', 'digest_version', 'insight_date'])),
                'digest_highlights' => $digest['highlights'] ?? [],
            ];
            $userPrompt = 'Output language (locale): '.$locale."\nContext JSON:\n".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $agent = agent(
                instructions: self::LLM_INSTRUCTIONS,
                messages: [],
                tools: [],
            );

            $response = $agent->prompt($userPrompt, [], AiTasks::provider('insight'));

            TokenUsageLogService::logFromAiResponse(
                teamId: (int) $team->id,
                service: 'UserDailyPerformanceInsightService',
                usage: $response->usage ?? null,
                moduleKey: 'performance_insights',
                inputSize: strlen($userPrompt),
            );

            $raw = trim((string) ($response->text ?? ''));
            if ($raw === '')
            {
                Log::warning('UserDailyPerformanceInsight: empty LLM response');

                return null;
            }

            $raw = preg_replace('/^```\w*\s*|\s*```$/u', '', $raw);
            $data = json_decode($raw, true);
            if (! is_array($data))
            {
                Log::warning('UserDailyPerformanceInsight: LLM returned non-JSON', ['raw' => mb_substr($raw, 0, 240)]);

                return null;
            }

            $headlineRaw = (string) ($data['headline'] ?? $data['title'] ?? '');
            $headline = $this->normalizeHeadlineWord($headlineRaw);
            $focus = $this->normalizeFocusPhrase((string) ($data['focus'] ?? ''));
            $message = $this->normalizeInsightMessage((string) ($data['message'] ?? ''));
            if ($headline === null || $focus === null || $message === null)
            {
                Log::warning('UserDailyPerformanceInsight: invalid headline, focus or message', ['raw' => mb_substr($raw, 0, 240)]);

                return null;
            }

            return [
                'headline' => $headline,
                'focus' => $focus,
                'message' => $message,
                'source' => 'llm',
            ];
        } catch (\Throwable $e)
        {
            // Transient provider issues (overloaded / rate limited) are expected and self-recover;
            // log them as warnings so they don't pollute the error log.
            $message = $e->getMessage();
            $isTransient = stripos($message, 'overloaded') !== false
                || stripos($message, 'rate limit') !== false
                || stripos($message, 'timed out') !== false
                || stripos($message, 'timeout') !== false;

            if ($isTransient)
            {
                Log::warning('UserDailyPerformanceInsight: LLM temporarily unavailable', ['error' => $message]);
            } else
            {
                Log::error('UserDailyPerformanceInsight: LLM failed', ['error' => $message]);
            }

            return null;
        }
    }

    /**
     * Rows created before three-field insights or legacy two-column copy must be recomputed.
     */
    private function storedInsightNeedsMicroUpgrade(UserDailyPerformanceInsight $row): bool
    {
        $snapshot = $row->context_snapshot ?? [];
        if (($snapshot['digest_version'] ?? 0) < DailyTeamDigestMetricsCollector::DIGEST_VERSION)
        {
            return true;
        }

        if (trim((string) ($row->focus ?? '')) === '')
        {
            return true;
        }

        $headline = $this->collapseWhitespaceAndLineBreaks((string) $row->headline);
        $headlineWords = preg_split('/\s+/u', $headline, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($headlineWords) !== 1)
        {
            return true;
        }

        $focusFlat = $this->collapseWhitespaceAndLineBreaks((string) $row->focus);
        $focusWords = preg_split('/\s+/u', $focusFlat, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($focusWords) !== 5)
        {
            return true;
        }

        $message = trim((string) $row->message);
        $messageLen = mb_strlen($message);
        if ($message === '' || $messageLen < 55 || $messageLen > 280)
        {
            return true;
        }

        return false;
    }

    private function collapseWhitespaceAndLineBreaks(string $s): string
    {
        $s = preg_replace('/\R+/u', ' ', $s) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    private function normalizeHeadlineWord(string $raw): ?string
    {
        $raw = $this->collapseWhitespaceAndLineBreaks($raw);
        if ($raw === '')
        {
            return null;
        }

        $wordPart = '';
        $suffixPart = '';

        if (preg_match('/\s/u', $raw))
        {
            $parts = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($parts) === 2 && $this->isSingleEmojiCluster($parts[1]))
            {
                $wordPart = $parts[0];
                $suffixPart = $parts[1];
            } else
            {
                return null;
            }
        } elseif (preg_match('/^([\p{L}\p{N}]+)(.*)$/u', $raw, $matches))
        {
            $wordPart = $matches[1];
            $suffixPart = $matches[2] ?? '';
        } else
        {
            return null;
        }

        $piece = trim($wordPart, " \t\n\r\0\x0B\"'.,;:!?¡¿");
        if ($piece === '' || ! preg_match('/^[\p{L}\p{N}]+$/u', $piece))
        {
            return null;
        }

        if (mb_strlen($piece) > 48)
        {
            return null;
        }

        if ($suffixPart !== '' && ! $this->isSingleEmojiCluster($suffixPart))
        {
            return null;
        }

        $lower = mb_strtolower($piece, 'UTF-8');
        foreach (self::FORBIDDEN_HEADLINE_TOKENS as $bad)
        {
            if ($lower === $bad)
            {
                return null;
            }
        }

        return $piece.$suffixPart;
    }

    /**
     * True when $s is a single user-perceived emoji (e.g. one flag or skin-tone sequence).
     */
    private function isSingleEmojiCluster(string $s): bool
    {
        $s = trim($s);
        if ($s === '')
        {
            return false;
        }

        if (function_exists('grapheme_strlen'))
        {
            if (grapheme_strlen($s) !== 1)
            {
                return false;
            }
        } elseif (mb_strlen($s) > 8)
        {
            return false;
        }

        return (bool) preg_match('/^\p{Extended_Pictographic}/u', $s);
    }

    private function normalizeFocusPhrase(string $raw): ?string
    {
        $preserveBreak = (bool) preg_match('/\R/u', $raw);
        $raw = $this->collapseWhitespaceAndLineBreaks($raw);
        if ($raw === '')
        {
            return null;
        }

        /** @var list<string> $words */
        $words = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < 5)
        {
            return null;
        }

        if (count($words) > 5)
        {
            $words = array_slice($words, 0, 5);
        }

        if (count($words) === 5 && $preserveBreak)
        {
            return $this->capitalizeFocusPhrase(
                implode(' ', array_slice($words, 0, 3))."\n".implode(' ', array_slice($words, 3, 2)),
            );
        }

        return $this->capitalizeFocusPhrase(implode(' ', $words));
    }

    private function capitalizeFocusPhrase(string $phrase): string
    {
        if ($phrase === '')
        {
            return $phrase;
        }

        return mb_strtoupper(mb_substr($phrase, 0, 1)).mb_substr($phrase, 1);
    }

    private function normalizeInsightMessage(string $raw): ?string
    {
        $raw = trim($this->collapseWhitespaceAndLineBreaks(strip_tags($raw)));
        if ($raw === '')
        {
            return null;
        }

        if (mb_strlen($raw) < 55)
        {
            return null;
        }

        if (mb_strlen($raw) > 240)
        {
            $chunk = mb_substr($raw, 0, 240);
            $lastSpace = mb_strrpos($chunk, ' ');
            if ($lastSpace !== false && $lastSpace >= 40)
            {
                $raw = rtrim(mb_substr($chunk, 0, $lastSpace), '.,;:!?¡¿');
            } else
            {
                $raw = rtrim($chunk, '.,;:!?¡¿');
            }
        }

        return mb_strlen($raw) >= 55 ? $raw : null;
    }

    /**
     * @return array{headline: string, focus: string, message: string, source: 'emergency'}
     */
    private function buildEmergencyInsight(float $ratio, string $locale, array $digest, ?string $mentoringHeadline): array
    {
        $tier = $this->performanceTier($ratio);
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try
        {
            $activity = $digest['user_activity'] ?? [];
            $mentoringLabel = ($mentoringHeadline !== null && trim($mentoringHeadline) !== '')
                ? trim($mentoringHeadline)
                : (string) __('app.performance_insight_emergency_mentoring_default');

            $replacements = [
                'interactions' => (int) ($activity['interactions_count'] ?? 0),
                'minutes' => (int) round((float) ($activity['call_minutes'] ?? 0)),
                'tasks' => (int) ($activity['tasks_done'] ?? 0),
                'mentoring_label' => $mentoringLabel,
            ];

            $highlights = $digest['highlights'] ?? [];
            if ($highlights !== [])
            {
                $focus = $this->normalizeFocusPhrase(implode(' ', array_slice(preg_split('/\s+/u', (string) $highlights[0], -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5))) ?? (string) __('app.performance_insight_emergency_focus_'.$tier);

                return [
                    'headline' => (string) __('app.performance_insight_emergency_headline_'.$tier),
                    'focus' => $focus,
                    'message' => (string) __('app.performance_insight_emergency_message_digest', [
                        'highlight' => $highlights[0],
                        'mentoring_label' => $mentoringLabel,
                    ]),
                    'source' => 'emergency',
                ];
            }

            $idleWeek = (int) ($activity['interactions_count'] ?? 0) === 0
                && (int) round((float) ($activity['call_minutes'] ?? 0)) === 0
                && (int) ($activity['tasks_done'] ?? 0) === 0;

            if ($tier === 'low' && $idleWeek)
            {
                $messageKey = 'app.performance_insight_emergency_message_low_idle';
            } elseif ($tier === 'low')
            {
                $messageKey = 'app.performance_insight_emergency_message_low_active';
            } else
            {
                $messageKey = 'app.performance_insight_emergency_message_'.$tier;
            }

            return [
                'headline' => (string) __('app.performance_insight_emergency_headline_'.$tier),
                'focus' => (string) __('app.performance_insight_emergency_focus_'.$tier),
                'message' => (string) __($messageKey, $replacements),
                'source' => 'emergency',
            ];
        } finally
        {
            app()->setLocale($previous);
        }
    }

    private function performanceTier(float $ratio): string
    {
        if ($ratio >= 70.0)
        {
            return 'high';
        }

        if ($ratio >= 40.0)
        {
            return 'mid';
        }

        return 'low';
    }
}
