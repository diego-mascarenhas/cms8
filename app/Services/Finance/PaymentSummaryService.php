<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentSummaryService
{
    public const ACTIONABLE_STATUSES = [1, 3];

    public const FILTER_ALL = 'all';

    public const FILTER_FAILED = 'failed';

    public const FILTER_ACTIONABLE = 'actionable';

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function applyStatusFilter(Builder $query, ?string $filter): Builder
    {
        $filter = $filter ?? self::FILTER_ALL;

        if ($filter === self::FILTER_ALL || $filter === '')
        {
            return $query;
        }

        if ($filter === self::FILTER_FAILED)
        {
            return $query->whereNotIn('status', [0, 1, 2, 3]);
        }

        if ($filter === self::FILTER_ACTIONABLE)
        {
            return $query->whereIn('status', self::ACTIONABLE_STATUSES);
        }

        if (in_array((int) $filter, [1, 2, 3], true))
        {
            return $query->where('status', (int) $filter);
        }

        return $query;
    }

    /**
     * @return array{
     *     total_count: int,
     *     in_process_count: int,
     *     pending_count: int,
     *     approved_count: int,
     *     actionable_count: int,
     *     failed_count: int,
     *     in_process_amount: float,
     *     pending_amount: float,
     *     approved_amount: float,
     *     failed_amount: float,
     *     pending_claim_count: int,
     * }
     */
    public function forTeam(Team $team): array
    {
        $rows = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status', '!=', 0)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('status')
            ->get();

        $byStatus = $rows->keyBy(fn ($row) => (int) $row->status);

        $inProcess = $this->metricsForStatus($byStatus, 1);
        $pending = $this->metricsForStatus($byStatus, 3);
        $approved = $this->metricsForStatus($byStatus, 2);
        $failed = $this->metricsForFailedStatuses($byStatus);

        return [
            'total_count' => (int) $rows->sum('count'),
            'in_process_count' => $inProcess['count'],
            'pending_count' => $pending['count'],
            'approved_count' => $approved['count'],
            'actionable_count' => $inProcess['count'] + $pending['count'],
            'failed_count' => $failed['count'],
            'in_process_amount' => $inProcess['amount'],
            'pending_amount' => $pending['amount'],
            'approved_amount' => $approved['amount'],
            'failed_amount' => $failed['amount'],
            'pending_claim_count' => $inProcess['count'] + $pending['count'],
        ];
    }

    /**
     * @param  Collection<int, object{status: int|string, count: int|string, total: float|string}>  $byStatus
     * @return array{count: int, amount: float}
     */
    private function metricsForStatus(Collection $byStatus, int $status): array
    {
        $row = $byStatus->get($status);

        return [
            'count' => (int) ($row->count ?? 0),
            'amount' => round((float) ($row->total ?? 0), 2),
        ];
    }

    /**
     * @param  Collection<int, object{status: int|string, count: int|string, total: float|string}>  $byStatus
     * @return array{count: int, amount: float}
     */
    private function metricsForFailedStatuses(Collection $byStatus): array
    {
        $failedRows = $byStatus->filter(
            fn ($row, $status) => ! in_array((int) $status, [1, 2, 3], true),
        );

        return [
            'count' => (int) $failedRows->sum('count'),
            'amount' => round((float) $failedRows->sum('total'), 2),
        ];
    }
}
