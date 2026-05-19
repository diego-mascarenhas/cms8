<?php

namespace App\Services;

use App\Enums\ContactInteractionType;
use App\Models\ContactInteraction;
use Illuminate\Support\Carbon;

class ContactInteractionChartDataService
{
    /**
     * @return array{labels: list<string>, values: list<int>, total: int}
     */
    public function buildTypeBreakdown(
        int $teamId,
        ?int $contactId = null,
        int $days = 30,
        ?int $responsibleId = null,
        bool $includeZeroTypes = false,
    ): array {
        $since = Carbon::now()->subDays($days)->startOfDay();

        $query = ContactInteraction::query()
            ->where('occurred_at', '>=', $since)
            ->whereHas('contact', function ($q) use ($teamId, $responsibleId): void
            {
                $q->where('team_id', $teamId);
                if ($responsibleId !== null)
                {
                    $q->where('responsible_id', $responsibleId);
                }
            });

        if ($contactId !== null)
        {
            $query->where('contact_id', $contactId);
        }

        $countsByType = [];
        foreach ($query->selectRaw('type, COUNT(*) as aggregate')->groupBy('type')->get() as $row)
        {
            $typeKey = $row->type instanceof ContactInteractionType
                ? $row->type->value
                : (string) $row->type;
            $countsByType[$typeKey] = (int) $row->aggregate;
        }

        $labels = [];
        $values = [];

        foreach (ContactInteractionType::cases() as $case)
        {
            $count = (int) ($countsByType[$case->value] ?? 0);
            if (! $includeZeroTypes && $count === 0)
            {
                continue;
            }

            $labels[] = $case->label();
            $values[] = $count;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
        ];
    }

    public function countForTeam(int $teamId, int $days = 30, ?int $responsibleId = null): int
    {
        $since = Carbon::now()->subDays($days)->startOfDay();

        return ContactInteraction::query()
            ->where('occurred_at', '>=', $since)
            ->whereHas('contact', function ($q) use ($teamId, $responsibleId): void
            {
                $q->where('team_id', $teamId);
                if ($responsibleId !== null)
                {
                    $q->where('responsible_id', $responsibleId);
                }
            })
            ->count();
    }

    /**
     * Daily stacked-bar trend: one series per interaction type with activity in the period.
     *
     * @return array{
     *     labels: list<string>,
     *     series: list<array{name: string, data: list<int>}>,
     *     total: int
     * }
     */
    public function buildDailyTrendByType(
        int $teamId,
        ?int $contactId = null,
        int $days = 30,
        ?int $responsibleId = null,
    ): array {
        $since = Carbon::now()->subDays($days)->startOfDay();

        $query = ContactInteraction::query()
            ->where('occurred_at', '>=', $since)
            ->whereHas('contact', function ($q) use ($teamId, $responsibleId): void
            {
                $q->where('team_id', $teamId);
                if ($responsibleId !== null)
                {
                    $q->where('responsible_id', $responsibleId);
                }
            });

        if ($contactId !== null)
        {
            $query->where('contact_id', $contactId);
        }

        $countsByDayAndType = [];
        $rows = $query
            ->selectRaw('date(occurred_at) as day, type, COUNT(*) as aggregate')
            ->groupBy('day', 'type')
            ->get();

        foreach ($rows as $row)
        {
            $typeKey = $row->type instanceof ContactInteractionType
                ? $row->type->value
                : (string) $row->type;
            $countsByDayAndType[$row->day][$typeKey] = (int) $row->aggregate;
        }

        $labels = [];
        $seriesByType = [];
        foreach (ContactInteractionType::cases() as $case)
        {
            $seriesByType[$case->value] = [
                'name' => $case->label(),
                'data' => [],
            ];
        }

        for ($dayOffset = $days - 1; $dayOffset >= 0; $dayOffset--)
        {
            $dayStart = Carbon::now()->subDays($dayOffset)->startOfDay();
            $dayKey = $dayStart->toDateString();
            $labels[] = $dayStart->isoFormat('D MMM');

            foreach (ContactInteractionType::cases() as $case)
            {
                $seriesByType[$case->value]['data'][] = (int) ($countsByDayAndType[$dayKey][$case->value] ?? 0);
            }
        }

        $series = [];
        $total = 0;
        foreach ($seriesByType as $typeSeries)
        {
            $typeTotal = array_sum($typeSeries['data']);
            if ($typeTotal === 0)
            {
                continue;
            }
            $total += $typeTotal;
            $series[] = $typeSeries;
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'total' => $total,
        ];
    }

    public function countForTeamBetween(
        int $teamId,
        Carbon $start,
        Carbon $end,
        ?int $responsibleId = null,
    ): int {
        return ContactInteraction::query()
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->whereHas('contact', function ($q) use ($teamId, $responsibleId): void
            {
                $q->where('team_id', $teamId);
                if ($responsibleId !== null)
                {
                    $q->where('responsible_id', $responsibleId);
                }
            })
            ->count();
    }
}
