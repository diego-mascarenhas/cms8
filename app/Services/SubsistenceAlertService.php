<?php

namespace App\Services;

use App\Enums\ContactInteractionType;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\ExchangeRate;
use App\Models\Team;
use App\Services\Finance\PaymentReportingCurrencyService;
use App\Support\RevisionAlphaOrganization;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SubsistenceAlertService
{
    /**
     * @return array{banners: list<array{key: string, level: string, icon: string, title: string, body: string}>}|null
     */
    public function forTeam(Team $team, ?CarbonInterface $now = null): ?array
    {
        if (! RevisionAlphaOrganization::appliesTo((int) $team->id))
        {
            return null;
        }

        $currency = app(PaymentReportingCurrencyService::class)->reportingCurrencyForTeam($team);
        $director = (float) config('organization.subsistence.hourly_eur.director', 28);
        $assistant = (float) config('organization.subsistence.hourly_eur.assistant', 12);

        if ($currency !== 'EUR')
        {
            $directorConverted = ExchangeRate::convert($director, 'EUR', $currency);
            $assistantConverted = ExchangeRate::convert($assistant, 'EUR', $currency);

            if ($directorConverted === null || $assistantConverted === null)
            {
                $currency = 'EUR';
            } else
            {
                $director = $directorConverted;
                $assistant = $assistantConverted;
            }
        }

        $moment = Carbon::parse($now ?? now());

        return $this->assess(
            RevisionAlphaOrganization::subsistenceCatalog(),
            $this->activity($team, $moment->copy()->timezone('Europe/Madrid')),
            $moment,
            $director,
            $assistant,
            $currency,
        );
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @param  array{
     *     calls_by_date?: array<string, int>,
     *     open_leads?: int,
     *     leads_entered_7d?: int,
     *     clients_created_30d?: int,
     *     publications_next_7d?: int
     * }  $activity
     * @return array{banners: list<array{key: string, level: string, icon: string, title: string, body: string}>}
     */
    public function assess(
        array $catalog,
        array $activity,
        CarbonInterface $now,
        float $directorHourly,
        float $assistantHourly,
        string $currency,
    ): array {
        $now = $now->copy()->timezone('Europe/Madrid');
        $iso = (int) $now->dayOfWeekIso;
        $window = $catalog['windows'][$iso] ?? ['starts_at' => null, 'ends_at' => null, 'minutes' => 0, 'minimum' => 0];
        $todayMinimum = (int) ($window['minimum'] ?? 0);
        $todayKey = $now->toDateString();
        $callsByDate = is_array($activity['calls_by_date'] ?? null) ? $activity['calls_by_date'] : [];
        $todayCalls = (int) ($callsByDate[$todayKey] ?? 0);
        $minutesPerCall = max(1, (int) ($catalog['minutes_per_call'] ?? 10));
        $windowState = 'none';
        $expectedNow = 0;

        if ($todayMinimum > 0 && filled($window['starts_at'] ?? null) && filled($window['ends_at'] ?? null))
        {
            $start = $now->copy()->setTimeFromTimeString((string) $window['starts_at']);
            $end = $now->copy()->setTimeFromTimeString((string) $window['ends_at']);

            if ($now->lt($start))
            {
                $windowState = 'before';
            } elseif ($now->gte($end))
            {
                $windowState = 'after';
                $expectedNow = $todayMinimum;
            } else
            {
                $windowState = 'during';
                $elapsed = (int) abs($start->diffInMinutes($now));
                $expectedNow = min($todayMinimum, intdiv($elapsed, $minutesPerCall));
            }
        }

        $pastZero = 0;
        $pastShort = 0;
        $pastMinimum = 0;
        $weekCalls = 0;
        $cursor = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $cursor->copy()->addDays(7);

        while ($cursor->lt($weekEnd))
        {
            $key = $cursor->toDateString();
            $got = (int) ($callsByDate[$key] ?? 0);
            $weekCalls += $got;

            if ($key < $todayKey)
            {
                $minimum = (int) ($catalog['windows'][$cursor->dayOfWeekIso]['minimum'] ?? 0);
                $pastMinimum += $minimum;

                if ($minimum > 0 && $got === 0)
                {
                    $pastZero++;
                } elseif ($minimum > 0 && $got < $minimum)
                {
                    $pastShort++;
                }
            }

            $cursor->addDay();
        }

        $todayLevel = null;

        if ($todayMinimum > 0)
        {
            if ($windowState === 'before')
            {
                $todayLevel = $todayCalls >= $todayMinimum ? 'success' : 'warning';
            } elseif ($windowState === 'during')
            {
                if ($todayCalls >= $todayMinimum || ($expectedNow > 0 && $todayCalls >= $expectedNow))
                {
                    $todayLevel = 'success';
                } elseif ($expectedNow === 0 || $todayCalls > 0)
                {
                    $todayLevel = 'warning';
                } else
                {
                    $todayLevel = 'danger';
                }
            } elseif ($todayCalls >= $todayMinimum)
            {
                $todayLevel = 'success';
            } elseif ($todayCalls > 0)
            {
                $todayLevel = 'warning';
            } else
            {
                $todayLevel = 'danger';
            }
        }

        $weekLevel = null;

        if ($pastMinimum > 0)
        {
            if ($pastZero > 0)
            {
                $weekLevel = 'danger';
            } elseif ($pastShort > 0)
            {
                $weekLevel = 'warning';
            } else
            {
                $weekLevel = 'success';
            }
        }

        $callLevel = $this->worstLevel($todayLevel, $weekLevel) ?? 'success';
        $openLeads = (int) ($activity['open_leads'] ?? 0);
        $posts = (int) ($activity['publications_next_7d'] ?? 0);
        $clients = (int) ($activity['clients_created_30d'] ?? 0);

        if ($posts > 0)
        {
            $marketingLevel = 'success';
        } elseif ($callLevel === 'danger' && $openLeads > 0)
        {
            $marketingLevel = 'danger';
        } else
        {
            $marketingLevel = 'warning';
        }

        if ($openLeads > 0 && $callLevel === 'danger')
        {
            $conversionLevel = 'danger';
        } elseif ($openLeads > 0 && $clients === 0)
        {
            $conversionLevel = 'warning';
        } elseif ($callLevel === 'danger')
        {
            $conversionLevel = 'warning';
        } else
        {
            $conversionLevel = 'success';
        }

        $displayStart = filled($window['starts_at'] ?? null) ? (string) $window['starts_at'] : (string) ($catalog['call_start'] ?? '');
        $displayEnd = filled($window['ends_at'] ?? null) ? (string) $window['ends_at'] : (string) ($catalog['call_end'] ?? '');
        $others = $this->otherWorkText($catalog['other_by_weekday'][$iso] ?? []);
        $callBody = __('app.subsistence_calls_body', [
            'call_tasks' => $this->taskText($catalog['tasks']['calls'] ?? []),
            'day_minimum' => (int) ($catalog['day_minimum'] ?? 0),
            'start' => $displayStart,
            'end' => $displayEnd,
            'owner' => (string) ($catalog['call_owner'] ?? ''),
            'minutes' => $minutesPerCall,
            'others' => $others,
            'week' => $weekCalls,
            'week_minimum' => (int) ($catalog['week_minimum'] ?? 0),
        ]);

        if ($windowState === 'none')
        {
            $callBody = __('app.subsistence_calls_off_today', [
                'owner' => (string) ($catalog['call_owner'] ?? ''),
                'others' => $others,
            ]).' '.$callBody;
        } elseif ($windowState === 'before')
        {
            $callBody = __('app.subsistence_calls_pending').' '.$callBody;
        }

        if ($todayMinimum > 0)
        {
            $callBody .= ' '.__('app.subsistence_calls_today', [
                'today' => $todayCalls,
                'minimum' => $todayMinimum,
            ]);
        }

        $banners = [
            [
                'key' => 'calls',
                'level' => $callLevel,
                'icon' => 'ti-phone-call',
                'title' => __('app.subsistence_calls_title_'.$callLevel),
                'body' => $callBody,
            ],
            [
                'key' => 'marketing',
                'level' => $marketingLevel,
                'icon' => 'ti-speakerphone',
                'title' => __('app.subsistence_marketing_title_'.$marketingLevel),
                'body' => __('app.subsistence_marketing_body', [
                    'tasks' => $this->taskText($catalog['tasks']['marketing'] ?? []),
                    'posts' => $posts,
                ]),
            ],
            [
                'key' => 'conversion',
                'level' => $conversionLevel,
                'icon' => 'ti-user-plus',
                'title' => __('app.subsistence_conversion_title_'.$conversionLevel),
                'body' => __('app.subsistence_conversion_body', [
                    'tasks' => $this->taskText($catalog['tasks']['conversion'] ?? []),
                    'open' => $openLeads,
                    'entered' => (int) ($activity['leads_entered_7d'] ?? 0),
                    'clients' => $clients,
                    'minimum' => (int) ($catalog['day_minimum'] ?? 0),
                ]),
            ],
        ];

        foreach ($catalog['people'] ?? [] as $person)
        {
            if (! is_array($person))
            {
                continue;
            }

            $hourly = ($person['role'] ?? '') === 'director' ? $directorHourly : $assistantHourly;
            $suggested = round($hourly * (float) ($person['assigned_hours'] ?? 0), 2);
            $levels = [];

            if ((float) ($person['calls_hours'] ?? 0) > 0)
            {
                $levels[] = $callLevel;
            }

            if ((float) ($person['marketing_hours'] ?? 0) > 0)
            {
                $levels[] = $marketingLevel;
            }

            if ((float) ($person['conversion_hours'] ?? 0) > 0)
            {
                $levels[] = $conversionLevel;
            }

            $level = $this->worstLevel(...$levels) ?? 'success';
            $amount = number_format($suggested, 2, ',', '.');
            $rate = number_format($hourly, 2, ',', '.');

            $banners[] = [
                'key' => 'salary-'.(string) ($person['key'] ?? ''),
                'level' => $level,
                'icon' => 'ti-cash',
                'title' => __('app.subsistence_salary_title', [
                    'name' => (string) ($person['name'] ?? ''),
                    'amount' => $amount,
                    'currency' => $currency,
                ]),
                'body' => __('app.subsistence_salary_body', [
                    'titles' => (string) ($person['titles'] ?? ''),
                    'assigned' => number_format((float) ($person['assigned_hours'] ?? 0), 1, ',', '.'),
                    'capacity' => number_format((float) ($person['capacity_hours'] ?? 0), 1, ',', '.'),
                    'calls' => number_format((float) ($person['calls_hours'] ?? 0), 1, ',', '.'),
                    'marketing' => number_format((float) ($person['marketing_hours'] ?? 0), 1, ',', '.'),
                    'conversion' => number_format((float) ($person['conversion_hours'] ?? 0), 1, ',', '.'),
                    'other' => number_format((float) ($person['other_hours'] ?? 0), 1, ',', '.'),
                    'amount' => $amount,
                    'hourly' => $rate,
                    'currency' => $currency,
                ]),
            ];
        }

        return ['banners' => $banners];
    }

    /**
     * @param  list<array{name: string, person: string, allocation: string, hours: float}>  $tasks
     */
    private function taskText(array $tasks): string
    {
        $lines = [];

        foreach ($tasks as $task)
        {
            $lines[] = $task['person'].' — '.$task['name'].' — '.$task['allocation'].' — '.number_format((float) $task['hours'], 1, ',', '.').' h';
        }

        return $lines === [] ? (string) __('app.subsistence_no_tasks') : implode('. ', $lines);
    }

    /**
     * @param  list<array{name: string, starts_at: string, ends_at: string}>  $blocks
     */
    private function otherWorkText(array $blocks): string
    {
        usort($blocks, static function (array $left, array $right): int
        {
            return strcmp((string) $left['starts_at'], (string) $right['starts_at']);
        });

        $lines = [];

        foreach ($blocks as $block)
        {
            $lines[] = $block['name'].' '.$block['starts_at'].'–'.$block['ends_at'];
        }

        return $lines === [] ? (string) __('app.subsistence_no_other_work') : implode('; ', $lines);
    }

    private function worstLevel(?string ...$levels): ?string
    {
        $rank = [
            'success' => 0,
            'warning' => 1,
            'danger' => 2,
        ];
        $worst = null;
        $score = -1;

        foreach ($levels as $level)
        {
            if ($level === null || ! isset($rank[$level]))
            {
                continue;
            }

            if ($rank[$level] > $score)
            {
                $score = $rank[$level];
                $worst = $level;
            }
        }

        return $worst;
    }

    /**
     * @return array{calls_by_date: array<string, int>, open_leads: int, leads_entered_7d: int, clients_created_30d: int, publications_next_7d: int}
     */
    private function activity(Team $team, CarbonInterface $madridNow): array
    {
        $weekStart = $madridNow->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(7);
        $rows = ContactInteraction::query()
            ->where('type', ContactInteractionType::Call->value)
            ->where('occurred_at', '>=', $weekStart->copy()->utc())
            ->where('occurred_at', '<', $weekEnd->copy()->utc())
            ->whereHas('contact', function ($query) use ($team): void
            {
                $query->withoutGlobalScope('team')
                    ->withoutGlobalScope('ownership')
                    ->where('team_id', $team->id);
            })
            ->get(['occurred_at']);

        $byDate = [];

        foreach ($rows as $row)
        {
            $key = $row->occurred_at?->timezone('Europe/Madrid')->toDateString();

            if ($key === null)
            {
                continue;
            }

            $byDate[$key] = ($byDate[$key] ?? 0) + 1;
        }

        $contacts = Contact::withoutGlobalScope('team')
            ->withoutGlobalScope('ownership')
            ->where('team_id', $team->id);

        return [
            'calls_by_date' => $byDate,
            'open_leads' => (clone $contacts)->whereIn('status_id', [1, 2])->count(),
            'leads_entered_7d' => (clone $contacts)->where('created_at', '>=', $madridNow->copy()->subDays(7)->utc())->count(),
            'clients_created_30d' => (clone $contacts)->where('status_id', 5)->where('created_at', '>=', $madridNow->copy()->subDays(30)->utc())->count(),
            'publications_next_7d' => $this->publicationsNext7Days($team, $madridNow),
        ];
    }

    private function publicationsNext7Days(Team $team, CarbonInterface $madridNow): int
    {
        $events = CalendarEvent::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('start', '>=', $madridNow->copy()->utc())
            ->where('start', '<=', $madridNow->copy()->addDays(7)->utc())
            ->get(['title', 'notes', 'label']);

        return $events->filter(fn (CalendarEvent $event): bool => $this->isPublication($event))->count();
    }

    private function isPublication(CalendarEvent $event): bool
    {
        $label = mb_strtolower(trim((string) $event->label));

        if (in_array($label, ['publicación', 'publicacion', 'publication', 'post'], true))
        {
            return true;
        }

        $text = mb_strtolower((string) $event->title.' '.(string) $event->notes);

        return preg_match('/publicaci[oó]n|\bposts?\b|instagram|linkedin|facebook|tiktok|\breel\b|\brrss\b/u', $text) === 1;
    }
}
