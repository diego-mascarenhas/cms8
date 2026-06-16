<?php

namespace App\Services\Fiscal\Cuentica;

use App\Models\InvoiceSync;

class CuenticaInvoiceCoreMapper
{
    /**
     * @return array{status: int, balance: float}
     */
    public function mapFromInvoiceSync(InvoiceSync $row): array
    {
        $status = strtolower(trim((string) $row->status));

        return [
            'status' => $this->mapStatus($status, $row->paid),
            'balance' => $this->mapBalance($status, $row->amount_remaining, $row->total, $row->paid),
        ];
    }

    public function mapStatus(string $cuenticaStatus, ?bool $paid = null): int
    {
        if ($paid === true && in_array($cuenticaStatus, ['open', ''], true))
        {
            return 2;
        }

        return match ($cuenticaStatus)
        {
            'draft' => 9,
            'open' => 1,
            'paid' => 2,
            'void' => 3,
            default => 1,
        };
    }

    public function mapBalance(
        string $cuenticaStatus,
        mixed $amountRemaining,
        mixed $total,
        ?bool $paid = null,
    ): float {
        $status = strtolower(trim($cuenticaStatus));

        if (in_array($status, ['paid', 'void'], true) || $paid === true)
        {
            return 0.0;
        }

        if ($amountRemaining !== null && $amountRemaining !== '')
        {
            return $this->normalizeAmount($amountRemaining);
        }

        return $this->normalizeAmount($total);
    }

    private function normalizeAmount(mixed $amount): float
    {
        $value = (float) $amount;
        if ($value < 0)
        {
            return 0.0;
        }

        return round($value, 2);
    }
}
