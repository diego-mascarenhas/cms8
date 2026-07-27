<?php

namespace App\Services\Billing;

use App\Models\InvoiceSync;

class StripeInvoiceCoreMapper
{
    /**
     * @return array{status: int, balance: float}
     */
    public function mapFromInvoiceSync(InvoiceSync $row): array
    {
        $stripeStatus = (string) $row->status;

        return [
            'status' => $this->mapStatus($stripeStatus, $row->paid),
            'balance' => $this->mapBalance(
                $stripeStatus,
                $row->amount_remaining,
                $row->total ?? $row->amount_due,
                $row->amount_paid,
                $row->paid,
            ),
        ];
    }

    public function mapStatus(string $stripeStatus, ?bool $paid = null): int
    {
        $status = strtolower(trim($stripeStatus));

        if ($paid === true && in_array($status, ['open', ''], true))
        {
            return 2;
        }

        return match ($status)
        {
            'draft' => 9,
            'open' => 1,
            'paid' => 2,
            'void' => 3,
            // Stripe credit notes use "issued"; local status 4 = Nota de Crédito.
            'issued' => 4,
            'uncollectible' => 7,
            default => 7,
        };
    }

    public function mapBalance(
        string $stripeStatus,
        mixed $amountRemaining,
        mixed $total,
        mixed $amountPaid = null,
        ?bool $paid = null,
    ): float {
        $status = strtolower(trim($stripeStatus));

        if (in_array($status, ['paid', 'void'], true) || $paid === true)
        {
            return 0.0;
        }

        if ($amountRemaining !== null && $amountRemaining !== '')
        {
            return $this->normalizeAmount($amountRemaining);
        }

        $totalNorm = $this->normalizeAmount($total);

        if ($amountPaid !== null && $amountPaid !== '')
        {
            return max(0.0, round($totalNorm - $this->normalizeAmount($amountPaid), 2));
        }

        return $totalNorm;
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
