<?php

namespace App\Services;

use App\Models\ContactInteraction;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\UserContactAction;
use App\Models\UserDailyPerformanceInsight;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

class UserDailyPerformanceInsightService
{
    private const LLM_INSTRUCTIONS = <<<'PROMPT'
You write a daily CRM performance insight for one user.
Reply with ONLY a JSON object (no markdown code fences, no extra text). Keys:
- "headline": exactly one word in the requested output language, optionally immediately followed by one emoji (no space) that fits the tone. Sharp stance (noun, imperative verb, or adjective). Never greetings or empty praise. Forbidden examples for the word part (any language): congratulations, bravo, hola, hello, welcome, felicidades, bienvenido, awesome, perfect, great job.
- "focus": exactly five words (single spaces), same language, the single priority to tackle today from the metrics. You may use at most one line break inside "focus" between words; total word count remains five across both lines.
- "message": exactly one short, upbeat paragraph in the same language (no bullets, no markdown). Aim for roughly 18–32 words—about half the length of a typical coaching note: warm, encouraging, never preachy or guilt-tripping. Weave the metrics lightly; do not open with dry score language (avoid leading with "ratio" or "0/100"). If "mentoring_phase" in the context JSON is non-null and non-empty, mention that phase once in a friendly way. Encourage one small concrete step aligned with "focus". No ALL CAPS.
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
        $metrics = $this->collectMetrics($user, $team);
        $ratio = $this->computePerformanceRatio($metrics);

        $insight = $this->resolveInsightPayload($user, $team, $metrics, $ratio, $mentoringHeadline, $locale);

        $snapshot = array_merge($metrics, array_filter([
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
     * @return array{interactions_count: int, call_minutes: float, tasks_done: int}
     */
    public function collectMetrics(User $user, Team $team): array
    {
        $since = now()->subDays(7)->startOfDay();

        $interactionsCount = ContactInteraction::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $since)
            ->whereHas('contact', function ($q) use ($team): void
            {
                $q->where('team_id', $team->id);
            })
            ->count();

        $seconds = (int) UserContactAction::query()
            ->where('user_id', $user->id)
            ->where('start_time', '>=', $since)
            ->whereHas('contact', function ($q) use ($team): void
            {
                $q->where('team_id', $team->id);
            })
            ->sum('duration_seconds');

        $doneStatusId = TaskStatus::query()->where('name', 'DONE')->value('id');
        $tasksDone = 0;
        if ($doneStatusId)
        {
            $tasksDone = (int) Task::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('responsible_id', $user->id)
                ->where('status_id', $doneStatusId)
                ->where('updated_at', '>=', $since)
                ->count();
        }

        return [
            'interactions_count' => $interactionsCount,
            'call_minutes' => max(0, $seconds / 60),
            'tasks_done' => $tasksDone,
        ];
    }

    public function computePerformanceRatio(array $metrics): float
    {
        $interactions = (int) $metrics['interactions_count'];
        $minutes = (float) $metrics['call_minutes'];
        $tasks = (int) $metrics['tasks_done'];

        $score = min(40, $interactions * 4) + min(35, ($minutes / 60) * 10) + min(25, $tasks * 8);

        return round(min(100, $score), 2);
    }

    public function buildMessage(array $metrics, float $ratio): string
    {
        $replacements = [
            'interactions' => (int) $metrics['interactions_count'],
            'minutes' => (int) round($metrics['call_minutes']),
            'tasks' => (int) $metrics['tasks_done'],
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
        $replacements = [
            'interactions' => (int) $metrics['interactions_count'],
            'minutes' => (int) round($metrics['call_minutes']),
            'tasks' => (int) $metrics['tasks_done'],
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
    private function resolveInsightPayload(User $user, Team $team, array $metrics, float $ratio, ?string $mentoringHeadline, string $locale): array
    {
        if (config('daily_performance_insight.use_llm', true))
        {
            $fromLlm = $this->tryBuildInsightWithLlm($user, $team, $metrics, $ratio, $mentoringHeadline, $locale);
            if ($fromLlm !== null)
            {
                return $fromLlm;
            }
        }

        return $this->buildEmergencyInsight($ratio, $locale, $metrics, $mentoringHeadline);
    }

    /**
     * @return ?array{headline: string, focus: string, message: string, source: 'llm'}
     */
    private function tryBuildInsightWithLlm(User $user, Team $team, array $metrics, float $ratio, ?string $mentoringHeadline, string $locale): ?array
    {
        try
        {
            $context = [
                'user_first_name' => preg_split('/\s+/u', trim($user->name), 2, PREG_SPLIT_NO_EMPTY)[0] ?? '',
                'user_full_name' => $user->name,
                'team_name' => $team->name,
                'mentoring_phase' => $mentoringHeadline,
                'rolling_7d_interactions' => $metrics['interactions_count'],
                'rolling_7d_call_minutes' => round((float) $metrics['call_minutes'], 1),
                'rolling_7d_tasks_done' => $metrics['tasks_done'],
                'performance_ratio_0_100' => $ratio,
            ];
            $userPrompt = 'Output language (locale): '.$locale."\nContext JSON:\n".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $agent = agent(
                instructions: self::LLM_INSTRUCTIONS,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userPrompt, [], Lab::Anthropic);
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
            Log::error('UserDailyPerformanceInsight: LLM failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Rows created before three-field insights or legacy two-column copy must be recomputed.
     */
    private function storedInsightNeedsMicroUpgrade(UserDailyPerformanceInsight $row): bool
    {
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
            return implode(' ', array_slice($words, 0, 3))."\n".implode(' ', array_slice($words, 3, 2));
        }

        return implode(' ', $words);
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
    private function buildEmergencyInsight(float $ratio, string $locale, array $metrics, ?string $mentoringHeadline): array
    {
        $tier = $this->performanceTier($ratio);
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try
        {
            $mentoringLabel = ($mentoringHeadline !== null && trim($mentoringHeadline) !== '')
                ? trim($mentoringHeadline)
                : (string) __('app.performance_insight_emergency_mentoring_default');

            $replacements = [
                'interactions' => (int) $metrics['interactions_count'],
                'minutes' => (int) round($metrics['call_minutes']),
                'tasks' => (int) $metrics['tasks_done'],
                'mentoring_label' => $mentoringLabel,
            ];

            $idleWeek = (int) $metrics['interactions_count'] === 0
                && (int) round((float) $metrics['call_minutes']) === 0
                && (int) $metrics['tasks_done'] === 0;

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
