<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Support\Collection;

class MercadoPagoInvoiceSuggestionService
{
    private const TOLERANCE = 0.02;

    /**
     * Suggest unpaid sell invoices (or combinations) that match a payment amount.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return list<array{invoice_ids: list<int>, label: string, total: float, kind: string}>
     */
    public function suggest(Collection $invoices, float $paymentAmount, int $maxSuggestions = 8): array
    {
        $paymentAmount = round($paymentAmount, 2);
        if ($paymentAmount <= 0 || $invoices->isEmpty())
        {
            return [];
        }

        $items = $invoices
            ->filter(fn (Invoice $invoice) => (float) $invoice->balance > 0)
            ->map(fn (Invoice $invoice) => [
                'id' => (int) $invoice->id,
                'balance' => round((float) $invoice->balance, 2),
                'number' => (string) ($invoice->number ?? '#'.$invoice->id),
                'date' => $this->formatInvoiceDate($invoice->date),
            ])
            ->values()
            ->take(40)
            ->all();

        $suggestions = [];

        foreach ($items as $item)
        {
            if ($this->amountsMatch($item['balance'], $paymentAmount))
            {
                $suggestions[] = $this->makeSuggestion(
                    [$item],
                    $paymentAmount,
                    'exact',
                );
            }
        }

        $count = count($items);
        for ($i = 0; $i < $count; $i++)
        {
            for ($j = $i + 1; $j < $count; $j++)
            {
                $pair = [$items[$i], $items[$j]];
                $sum = round($pair[0]['balance'] + $pair[1]['balance'], 2);
                if ($this->amountsMatch($sum, $paymentAmount))
                {
                    $suggestions[] = $this->makeSuggestion($pair, $paymentAmount, 'combo');
                }
            }
        }

        if ($count <= 25)
        {
            for ($i = 0; $i < $count; $i++)
            {
                for ($j = $i + 1; $j < $count; $j++)
                {
                    for ($k = $j + 1; $k < $count; $k++)
                    {
                        $triple = [$items[$i], $items[$j], $items[$k]];
                        $sum = round($triple[0]['balance'] + $triple[1]['balance'] + $triple[2]['balance'], 2);
                        if ($this->amountsMatch($sum, $paymentAmount))
                        {
                            $suggestions[] = $this->makeSuggestion($triple, $paymentAmount, 'combo');
                        }
                    }
                }
            }
        }

        $unique = [];
        $seen = [];
        foreach ($suggestions as $suggestion)
        {
            $key = implode(',', $suggestion['invoice_ids']);
            if (isset($seen[$key]))
            {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $suggestion;
            if (count($unique) >= $maxSuggestions)
            {
                break;
            }
        }

        return $unique;
    }

    private function formatInvoiceDate(mixed $date): string
    {
        if ($date instanceof \Carbon\CarbonInterface)
        {
            return $date->format('d/m/Y');
        }

        if (is_string($date) && trim($date) !== '')
        {
            try
            {
                return \Carbon\Carbon::parse($date)->format('d/m/Y');
            } catch (\Throwable)
            {
                return $date;
            }
        }

        return '';
    }

    /**
     * @param  list<array{id: int, balance: float, number: string, date: string}>  $items
     * @return array{invoice_ids: list<int>, label: string, total: float, kind: string}
     */
    private function makeSuggestion(array $items, float $paymentAmount, string $kind): array
    {
        $ids = array_map(fn (array $item) => $item['id'], $items);
        $total = round(array_sum(array_map(fn (array $item) => $item['balance'], $items)), 2);
        $parts = array_map(
            fn (array $item) => trim($item['number'].($item['date'] !== '' ? ' ('.$item['date'].')' : '').' · '.number_format($item['balance'], 2, ',', '.')),
            $items,
        );

        return [
            'invoice_ids' => $ids,
            'label' => implode(' + ', $parts),
            'total' => $total,
            'kind' => $kind,
        ];
    }

    private function amountsMatch(float $left, float $right): bool
    {
        return abs(round($left, 2) - round($right, 2)) <= self::TOLERANCE;
    }
}
