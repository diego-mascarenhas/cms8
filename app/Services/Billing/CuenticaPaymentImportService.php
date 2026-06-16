<?php

namespace App\Services\Billing;

use App\Enums\CuenticaInboundDocumentKind;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use Carbon\Carbon;

class CuenticaPaymentImportService
{
    public function syncPaymentForInvoice(
        Invoice $invoice,
        InvoiceSync $row,
        bool $dryRun = false,
    ): ?Payment {
        if ($invoice->source_provider !== 'cuentica' || ! filled($invoice->enterprise_id))
        {
            return null;
        }

        if ($this->invoiceHasApprovedPayment($invoice, $row))
        {
            return null;
        }

        $amount = $this->resolvePaymentAmount($row);
        if ($amount <= 0)
        {
            return null;
        }

        $kind = CuenticaInboundDocumentKind::fromBillingReason($row->billing_reason)
            ?? CuenticaInboundDocumentKind::fromExternalId($row->external_id);

        if ($kind === null)
        {
            return null;
        }

        $transactionType = $kind === CuenticaInboundDocumentKind::Sale
            ? TransactionType::INCOME
            : TransactionType::EXPENSE;

        $sourceReferenceId = 'cuentica-invoice:'.$row->external_id;
        $date = $this->resolvePaymentDate($row);
        $remarks = $this->resolveRemarks($invoice, $kind);

        if ($dryRun)
        {
            return null;
        }

        $accountId = $this->ensureCuenticaPaymentAccount($invoice->team_id);
        $typeId = $this->resolveCuenticaPaymentTypeId();
        if ($accountId === null || $typeId === null)
        {
            return null;
        }

        $existing = Payment::withoutGlobalScopes()
            ->where('team_id', $invoice->team_id)
            ->where('source_provider', 'cuentica')
            ->where('source_reference_id', $sourceReferenceId)
            ->first();

        $payload = [
            'enterprise_id' => $invoice->enterprise_id,
            'transaction_type' => $transactionType,
            'date' => $date,
            'invoice_id' => $invoice->id,
            'account_id' => $accountId,
            'type_id' => $typeId,
            'amount' => round($amount, 2),
            'remarks' => $remarks,
            'status' => 2,
            'source_provider' => 'cuentica',
            'source_reference_id' => $sourceReferenceId,
            'source_synced_at' => $row->last_synced_at ?? now(),
        ];

        if ($existing)
        {
            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        return Payment::withoutGlobalScopes()->create(array_merge(
            $payload,
            ['team_id' => $invoice->team_id],
        ));
    }

    public function invoiceHasApprovedPayment(Invoice $invoice, InvoiceSync $row): bool
    {
        $sourceReferenceId = 'cuentica-invoice:'.$row->external_id;

        return Payment::withoutGlobalScopes()
            ->where('team_id', $invoice->team_id)
            ->where('status', '!=', 0)
            ->where(function ($query) use ($invoice, $sourceReferenceId): void
            {
                $query->where('invoice_id', $invoice->id)
                    ->orWhere('source_reference_id', $sourceReferenceId);
            })
            ->exists();
    }

    public function resolvePaymentAmount(InvoiceSync $row): float
    {
        $fromLines = $this->sumPaidLines($row);
        if ($fromLines > 0)
        {
            return round($fromLines, 2);
        }

        if (! $this->invoiceSyncIsFullyPaid($row))
        {
            return 0.0;
        }

        $amountPaid = (float) ($row->amount_paid ?? 0);
        if ($amountPaid > 0)
        {
            return round($amountPaid, 2);
        }

        return round((float) ($row->total ?? 0), 2);
    }

    private function invoiceSyncIsFullyPaid(InvoiceSync $row): bool
    {
        if ($row->paid === true)
        {
            return true;
        }

        return strtolower(trim((string) $row->status)) === 'paid';
    }

    private function sumPaidLines(InvoiceSync $row): float
    {
        $payload = is_array($row->raw_payload) ? $row->raw_payload : [];
        $key = str_starts_with($row->external_id, CuenticaInboundDocumentKind::Purchase->value.':')
            ? 'payments'
            : 'charges';
        $lines = $payload[$key] ?? [];
        $total = 0.0;

        foreach ($lines as $line)
        {
            if (! is_array($line) || ! ($line['paid'] ?? false))
            {
                continue;
            }

            $total += max(0.0, (float) ($line['amount'] ?? 0));
        }

        return round($total, 2);
    }

    private function resolvePaymentDate(InvoiceSync $row): string
    {
        $payload = is_array($row->raw_payload) ? $row->raw_payload : [];
        $key = str_starts_with($row->external_id, CuenticaInboundDocumentKind::Purchase->value.':')
            ? 'payments'
            : 'charges';
        $lines = $payload[$key] ?? [];
        $dates = [];

        foreach ($lines as $line)
        {
            if (! is_array($line) || ! ($line['paid'] ?? false))
            {
                continue;
            }

            $lineDate = $line['date'] ?? null;
            if (filled($lineDate))
            {
                $dates[] = Carbon::parse((string) $lineDate)->toDateString();
            }
        }

        if ($dates !== [])
        {
            return max($dates);
        }

        if ($row->invoice_created_at)
        {
            return Carbon::parse($row->invoice_created_at)->toDateString();
        }

        return now()->toDateString();
    }

    private function resolveRemarks(Invoice $invoice, CuenticaInboundDocumentKind $kind): string
    {
        $label = $kind === CuenticaInboundDocumentKind::Sale ? 'sale' : 'purchase';

        return 'Cuéntica '.$label.' '.$invoice->number;
    }

    private function ensureCuenticaPaymentAccount(int $teamId): ?int
    {
        $account = PaymentAccount::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'code' => 'cuentica',
            ],
            [
                'name' => 'Cuéntica',
                'symbol' => null,
                'currency_id' => null,
                'status' => 1,
            ],
        );

        return (int) $account->id;
    }

    private function resolveCuenticaPaymentTypeId(): ?int
    {
        $id = PaymentType::query()->where('name', 'Cuéntica')->value('id');
        if ($id !== null)
        {
            return (int) $id;
        }

        $fallback = PaymentType::query()->orderBy('id')->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }
}
