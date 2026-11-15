<?php

namespace App\Services\Finance;

use App\Models\BankStatementLine;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Support\SearchNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BankStatementInvoiceCandidateService
{
    /**
     * Rank open invoices for a statement line: enterprise/alias match, then amount, then date.
     *
     * @return array{
     *     operation: string,
     *     amount: float,
     *     suggested_invoice_id: int|null,
     *     suggested_remarks: string|null,
     *     invoices: list<array<string, mixed>>
     * }
     */
    public function candidatesForLine(BankStatementLine $line, int $teamId, string $search = ''): array
    {
        $isExpense = (float) $line->amount < 0;
        $operation = $isExpense ? 'buy' : 'sell';
        $amount = abs(round((float) $line->amount, 2));
        $lineText = trim(implode(' ', array_values(array_unique(array_filter([
            trim((string) ($line->reference ?? '')),
            trim((string) ($line->description ?? '')),
            trim((string) ($line->payer_name ?? '')),
        ], static fn (string $part): bool => $part !== '')))));
        $lineHaystack = SearchNormalizer::normalize($lineText);
        $lineDate = $line->occurred_at ? Carbon::parse($line->occurred_at)->startOfDay() : null;

        $query = Invoice::query()
            ->with(['enterprise:id,name,data', 'currency:id,code'])
            ->where('team_id', $teamId)
            ->where('operation', $operation)
            ->where('balance', '>', 0)
            ->orderByDesc('date')
            ->orderByDesc('id');

        $search = trim($search);

        if ($search !== '')
        {
            $query->where(function ($builder) use ($search): void
            {
                SearchNormalizer::applyColumnsNavbarConditions($builder, ['number'], $search);
                $builder->orWhereHas('enterprise', function ($enterpriseQuery) use ($search): void
                {
                    SearchNormalizer::applyColumnsNavbarConditions($enterpriseQuery, ['name'], $search);
                });
            });
        }

        /** @var Collection<int, Invoice> $invoices */
        $invoices = $query
            ->limit(80)
            ->get(['id', 'number', 'date', 'total_amount', 'balance', 'currency_id', 'enterprise_id', 'operation']);

        $ranked = $invoices
            ->map(function (Invoice $invoice) use ($lineHaystack, $amount, $lineDate): array
            {
                $enterprise = $invoice->enterprise;
                $enterpriseScore = $this->enterpriseMatchScore($enterprise, $lineHaystack);
                $balance = round((float) $invoice->balance, 2);
                $total = round((float) $invoice->total_amount, 2);
                $amountDelta = min(abs($balance - $amount), abs($total - $amount));
                $amountScore = max(0, 50 - min(50, (int) round($amountDelta)));

                $date = filled($invoice->date) ? Carbon::parse($invoice->date)->startOfDay() : null;
                $days = ($date && $lineDate) ? abs($date->diffInDays($lineDate)) : 999;
                $dateScore = $days === 999 ? 0 : max(0, 30 - min(30, (int) $days));

                $score = ($enterpriseScore * 1000) + ($amountScore * 10) + $dateScore;

                return [
                    'invoice' => $invoice,
                    'enterprise_score' => $enterpriseScore,
                    'amount_score' => $amountScore,
                    'date_score' => $dateScore,
                    'score' => $score,
                ];
            })
            ->sort(function (array $left, array $right): int
            {
                return $right['score'] <=> $left['score']
                    ?: $right['enterprise_score'] <=> $left['enterprise_score']
                    ?: $right['amount_score'] <=> $left['amount_score']
                    ?: $right['date_score'] <=> $left['date_score'];
            })
            ->values()
            ->take(20);

        $rows = $ranked->map(function (array $row): array
        {
            /** @var Invoice $invoice */
            $invoice = $row['invoice'];
            $date = $invoice->date;

            return [
                'id' => (int) $invoice->id,
                'number' => (string) $invoice->number,
                'date' => filled($date) ? Carbon::parse($date)->format('Y-m-d') : null,
                'enterprise_name' => (string) ($invoice->enterprise?->name ?? ''),
                'total_amount' => round((float) $invoice->total_amount, 2),
                'balance' => round((float) $invoice->balance, 2),
                'currency_code' => strtoupper((string) ($invoice->currency?->code ?? '')),
                'operation' => (string) $invoice->operation,
                'match_score' => (int) $row['score'],
                'matched_by_enterprise' => (int) $row['enterprise_score'] > 0,
            ];
        })->all();

        $top = $ranked->first();
        $suggestedId = null;
        $suggestedRemarks = $lineText !== '' ? $lineText : null;

        if (is_array($top) && (int) $top['enterprise_score'] > 0)
        {
            /** @var Invoice $suggestedInvoice */
            $suggestedInvoice = $top['invoice'];
            $suggestedId = (int) $suggestedInvoice->id;
            $enterpriseRemarks = $suggestedInvoice->enterprise?->bankRemarks();

            if (filled($enterpriseRemarks))
            {
                $suggestedRemarks = $enterpriseRemarks;
            }
        } elseif (is_array($top) && (int) $top['amount_score'] >= 45 && (int) $top['date_score'] >= 20)
        {
            $suggestedId = (int) $top['invoice']->id;
        }

        return [
            'operation' => $operation,
            'amount' => $amount,
            'suggested_invoice_id' => $suggestedId,
            'suggested_remarks' => $suggestedRemarks,
            'invoices' => $rows,
        ];
    }

    private function enterpriseMatchScore(?Enterprise $enterprise, string $lineHaystack): int
    {
        if (! $enterprise || $lineHaystack === '')
        {
            return 0;
        }

        $score = 0;

        foreach ($enterprise->bankAliases() as $alias)
        {
            $normalizedAlias = SearchNormalizer::normalize($alias);

            if ($normalizedAlias !== '' && str_contains($lineHaystack, $normalizedAlias))
            {
                $score = max($score, mb_strlen($normalizedAlias) >= 6 ? 5 : 3);
            }
        }

        $name = SearchNormalizer::normalize((string) $enterprise->name);

        if ($name !== '' && str_contains($lineHaystack, $name))
        {
            $score = max($score, 4);
        }

        foreach (SearchNormalizer::significantTokens((string) $enterprise->name) as $token)
        {
            if (mb_strlen($token) < 4)
            {
                continue;
            }

            if (str_contains($lineHaystack, $token))
            {
                $score = max($score, 2);
            }
        }

        return $score;
    }
}
