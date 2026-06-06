<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class InvoiceLegacyBonificationService
{
    /** @var list<int> */
    public const BONIFIED_STATUSES = [5, 6];

    /** @var list<int> */
    public const TERMINAL_STATUSES = [3, 4, 5, 6, 7, 9];

    /**
     * @return Builder<Invoice>
     */
    public function pendingLegacyQuery(
        ?int $teamId = null,
        ?int $untilYear = null,
        ?int $fromYear = null,
    ): Builder {
        $query = Invoice::withoutGlobalScopes()
            ->where('balance', '>', 0)
            ->whereNotIn('status', self::TERMINAL_STATUSES)
            ->where(function (Builder $builder): void
            {
                $builder
                    ->whereNull('source_provider')
                    ->orWhere('source_provider', 'manual');
            });

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        }

        if ($fromYear !== null)
        {
            $query->whereYear('date', '>=', $fromYear);
        }

        if ($untilYear !== null)
        {
            $query->whereYear('date', '<=', $untilYear);
        }

        return $query->orderBy('date')->orderBy('id');
    }

    /**
     * @return Builder<Invoice>
     */
    public function inconsistentBonifiedQuery(?int $teamId = null): Builder
    {
        $query = Invoice::withoutGlobalScopes()
            ->whereIn('status', self::BONIFIED_STATUSES)
            ->where('balance', '>', 0);

        if ($teamId !== null)
        {
            $query->where('team_id', $teamId);
        }

        return $query->orderBy('date')->orderBy('id');
    }

    /**
     * @return array{
     *     executed_at: string,
     *     dry_run: bool,
     *     filters: array{team_id: int|null, from_year: int|null, until_year: int|null},
     *     summary: array{
     *         bonified: array{matched: int, updated: int},
     *         balance_zeroed: array{matched: int, updated: int},
     *     },
     *     bonified_invoices: list<array<string, mixed>>,
     *     balance_zeroed_invoices: list<array<string, mixed>>,
     * }
     */
    public function runCorrection(
        ?int $teamId = null,
        ?int $untilYear = null,
        ?int $fromYear = null,
        bool $dryRun = false,
        bool $fixBonifiedBalances = true,
    ): array {
        $pending = $this->pendingLegacyQuery($teamId, $untilYear, $fromYear)->get();
        $inconsistent = $fixBonifiedBalances
            ? $this->inconsistentBonifiedQuery($teamId)->get()
            : collect();

        $bonifiedLog = $pending
            ->map(fn (Invoice $invoice): array => $this->snapshotBonification($invoice))
            ->values()
            ->all();

        $balanceZeroedLog = $inconsistent
            ->map(fn (Invoice $invoice): array => $this->snapshotBalanceZeroed($invoice))
            ->values()
            ->all();

        $updatedBonified = 0;
        $updatedBalanceZeroed = 0;

        if (! $dryRun && ($pending->isNotEmpty() || $inconsistent->isNotEmpty()))
        {
            Invoice::query()->getModel()->getConnection()->transaction(function () use (
                $pending,
                $inconsistent,
                &$updatedBonified,
                &$updatedBalanceZeroed,
            ): void {
                foreach ($pending as $invoice)
                {
                    if (! $invoice instanceof Invoice)
                    {
                        continue;
                    }

                    $invoice->status = 5;
                    $invoice->balance = 0;
                    $invoice->save();
                    $updatedBonified++;
                }

                foreach ($inconsistent as $invoice)
                {
                    if (! $invoice instanceof Invoice)
                    {
                        continue;
                    }

                    $invoice->balance = 0;
                    $invoice->save();
                    $updatedBalanceZeroed++;
                }
            });
        }

        return [
            'executed_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'filters' => [
                'team_id' => $teamId,
                'from_year' => $fromYear,
                'until_year' => $untilYear,
            ],
            'summary' => [
                'bonified' => [
                    'matched' => $pending->count(),
                    'updated' => $updatedBonified,
                ],
                'balance_zeroed' => [
                    'matched' => $inconsistent->count(),
                    'updated' => $updatedBalanceZeroed,
                ],
            ],
            'bonified_invoices' => $bonifiedLog,
            'balance_zeroed_invoices' => $balanceZeroedLog,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotBonification(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'date' => $invoice->date,
            'team_id' => $invoice->team_id,
            'enterprise_id' => $invoice->enterprise_id,
            'source_provider' => $invoice->source_provider,
            'previous_status' => (int) $invoice->status,
            'previous_balance' => (float) $invoice->balance,
            'new_status' => 5,
            'new_balance' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotBalanceZeroed(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'date' => $invoice->date,
            'team_id' => $invoice->team_id,
            'enterprise_id' => $invoice->enterprise_id,
            'source_provider' => $invoice->source_provider,
            'status' => (int) $invoice->status,
            'previous_balance' => (float) $invoice->balance,
            'new_balance' => 0.0,
        ];
    }
}
