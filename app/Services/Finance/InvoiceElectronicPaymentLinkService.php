<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\User;
use App\Services\Billing\MercadoPagoPaymentImportService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvoiceElectronicPaymentLinkService
{
    /** @var list<int> */
    private const BLOCKED_STATUSES = [3, 4, 5, 6, 7, 9];

    public function __construct(
        private readonly MercadoPagoPaymentImportService $mercadoPagoPaymentImportService,
        private readonly InvoicePaymentRegistrationService $invoicePaymentRegistrationService,
    ) {}

    public function canLink(User $user, Invoice $invoice): bool
    {
        if (! $user->currentTeam || (int) $invoice->team_id !== (int) $user->currentTeam->id)
        {
            return false;
        }

        if (! $user->ownsTeam($user->currentTeam))
        {
            return false;
        }

        if ((float) $invoice->balance <= 0)
        {
            return false;
        }

        if (in_array((int) $invoice->status, self::BLOCKED_STATUSES, true))
        {
            return false;
        }

        return ! $this->invoicePaymentRegistrationService->isStripeInvoiceCollected($invoice);
    }

    /**
     * Approved Mercado Pago syncs not yet imported, preferred by currency / amount closeness.
     *
     * @return Collection<int, PaymentSync>
     */
    public function availableSyncs(Invoice $invoice, int $limit = 40): Collection
    {
        $currency = strtoupper((string) ($invoice->currency_code ?: 'ARS'));
        $balance = round((float) $invoice->balance, 2);

        $syncs = PaymentSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'mercadopago')
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->where('amount_net_cents', '>', 0)
            ->orderByDesc('charge_created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(fn (PaymentSync $sync) => ! $this->mercadoPagoPaymentImportService->isAlreadyImported($sync))
            ->values();

        return $syncs
            ->sortBy(function (PaymentSync $sync) use ($currency, $balance): array
            {
                $syncCurrency = strtoupper((string) $sync->currency);
                $amount = $this->amountMajor($sync);
                $currencyRank = $syncCurrency === $currency ? 0 : 1;
                $amountDelta = abs($amount - $balance);

                return [$currencyRank, $amountDelta, -1 * (optional($sync->charge_created_at)?->timestamp ?? 0)];
            })
            ->take($limit)
            ->values();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function syncOptions(Invoice $invoice): array
    {
        return $this->availableSyncs($invoice)
            ->map(fn (PaymentSync $sync) => [
                'id' => (int) $sync->id,
                'label' => $this->labelForSync($sync),
            ])
            ->all();
    }

    public function link(User $user, Invoice $invoice, PaymentSync $sync): Payment
    {
        if (! $this->canLink($user, $invoice))
        {
            throw ValidationException::withMessages([
                'payment_sync_id' => __('invoice_payment.errors.not_allowed'),
            ]);
        }

        if ((int) $sync->team_id !== (int) $invoice->team_id
            || strtolower((string) $sync->provider) !== 'mercadopago')
        {
            throw ValidationException::withMessages([
                'payment_sync_id' => __('invoice_payment.errors.sync_invalid'),
            ]);
        }

        if (strtolower((string) $sync->status) !== 'approved')
        {
            throw ValidationException::withMessages([
                'payment_sync_id' => __('invoice_payment.errors.sync_not_approved'),
            ]);
        }

        if ($this->mercadoPagoPaymentImportService->isAlreadyImported($sync))
        {
            throw ValidationException::withMessages([
                'payment_sync_id' => __('invoice_payment.errors.sync_already_imported'),
            ]);
        }

        if (! $invoice->enterprise_id)
        {
            throw ValidationException::withMessages([
                'payment_sync_id' => __('invoice_payment.errors.invoice_without_enterprise'),
            ]);
        }

        $payment = $this->mercadoPagoPaymentImportService->importFromPaymentSync(
            $sync,
            fallbackEmail: false,
            linkCodeOnEmailMatch: false,
            dryRun: false,
            forceEnterpriseId: (int) $invoice->enterprise_id,
            forceInvoiceIds: [(int) $invoice->id],
        );

        if (! $payment instanceof Payment)
        {
            throw ValidationException::withMessages([
                'payment_sync_id' => __('invoice_payment.errors.sync_import_failed'),
            ]);
        }

        return $payment;
    }

    public function labelForSync(PaymentSync $sync): string
    {
        $date = $sync->charge_created_at?->format('d/m/Y') ?? '—';
        $amount = number_format($this->amountMajor($sync), 2, ',', '.');
        $currency = strtoupper((string) $sync->currency);
        $parts = [
            $date,
            $amount.' '.$currency,
            (string) $sync->external_id,
        ];

        $identification = $sync->identificationCode();
        if ($identification !== null)
        {
            $parts[] = $identification;
        }

        return implode(' · ', $parts);
    }

    private function amountMajor(PaymentSync $sync): float
    {
        $currency = strtoupper((string) $sync->currency);
        $cents = (int) $sync->amount_net_cents;

        if (in_array($currency, ['CLP', 'UYU', 'PYG'], true))
        {
            return (float) $cents;
        }

        return round($cents / 100, 2);
    }
}
