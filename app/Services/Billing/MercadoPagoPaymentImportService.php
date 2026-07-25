<?php

namespace App\Services\Billing;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentSync;
use App\Models\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MercadoPagoPaymentImportService
{
    public function __construct(
        private readonly StripeInvoiceOutOfBandPaymentService $stripeOutOfBandPaymentService,
        private readonly StripeInvoiceCoreImportService $stripeInvoiceCoreImportService,
    ) {}

    /**
     * One Mercado Pago transfer already tagged on one or more Stripe invoices
     * (mercadopago_id / payment_reference) but missing Humano payment rows.
     */
    public function importFromExistingStripeMetadataLinks(PaymentSync $row): ?Payment
    {
        if (strtolower((string) $row->provider) !== 'mercadopago')
        {
            return null;
        }

        if (strtolower((string) $row->status) !== 'approved')
        {
            return null;
        }

        if ($this->isAlreadyImported($row))
        {
            return $row->importedMercadoPagoPayment();
        }

        $invoices = $this->materializeLinkedStripeInvoices($row);
        if ($invoices->isEmpty())
        {
            return null;
        }

        $enterpriseIds = $invoices->pluck('enterprise_id')->filter()->unique()->values();
        if ($enterpriseIds->count() !== 1)
        {
            return null;
        }

        return $this->importFromPaymentSync(
            $row,
            fallbackEmail: false,
            linkCodeOnEmailMatch: false,
            dryRun: false,
            forceEnterpriseId: (int) $enterpriseIds->first(),
            forceInvoiceIds: $invoices->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    public function materializeLinkedStripeInvoices(PaymentSync $row)
    {
        $invoices = collect();

        foreach ($this->findLinkedStripeInvoiceSyncs($row) as $invoiceSync)
        {
            if (! $invoiceSync instanceof InvoiceSync)
            {
                continue;
            }

            $invoice = Invoice::withoutGlobalScopes()
                ->where('team_id', $row->team_id)
                ->where('source_provider', 'stripe')
                ->where('source_reference_id', $invoiceSync->external_id)
                ->first();

            if (! $invoice instanceof Invoice)
            {
                $invoice = $this->stripeInvoiceCoreImportService->importFromSyncRow(
                    $invoiceSync,
                    fallbackEmail: true,
                    linkCodeOnEmailMatch: false,
                    dryRun: false,
                );
            }

            if ($invoice instanceof Invoice)
            {
                $invoices->push($invoice);
            }
        }

        return $invoices->unique('id')->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, InvoiceSync>
     */
    public function findLinkedStripeInvoiceSyncs(PaymentSync $row)
    {
        $references = $row->stripeMatchReferences();
        if ($references === [])
        {
            return collect();
        }

        return InvoiceSync::query()
            ->where('team_id', $row->team_id)
            ->where('provider', 'stripe')
            ->where(function ($query) use ($references): void
            {
                foreach ($references as $reference)
                {
                    $query->orWhereRaw(
                        "TRIM(COALESCE(raw_payload->'metadata'->>'payment_reference', '')) = ?",
                        [$reference],
                    )->orWhereRaw(
                        "TRIM(COALESCE(
                            raw_payload->'metadata'->>'mercadopago_id',
                            raw_payload->'metadata'->>'mercadopago_payment_id',
                            ''
                        )) = ?",
                        [$reference],
                    );
                }
            })
            ->orderBy('invoice_created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * When a Stripe invoice was marked paid out of band with a Mercado Pago reference,
     * create the matching Humano payment(s) if still missing (full MP transfer, all invoices).
     */
    public function importOutOfBandLinkForStripeInvoice(Invoice $invoice): ?Payment
    {
        if (strtolower((string) $invoice->source_provider) !== 'stripe')
        {
            return null;
        }

        $stripeExternalId = trim((string) $invoice->source_reference_id);
        if ($stripeExternalId === '' || ! $invoice->enterprise_id)
        {
            return null;
        }

        $sync = $this->findMercadoPagoSyncForStripeInvoice(
            (int) $invoice->team_id,
            $stripeExternalId,
        );
        if (! $sync instanceof PaymentSync)
        {
            return null;
        }

        if ($this->isAlreadyImported($sync))
        {
            return $sync->importedMercadoPagoPayment();
        }

        return $this->importFromExistingStripeMetadataLinks($sync);
    }

    /**
     * @param  list<int>  $forceInvoiceIds
     */
    public function importFromPaymentSync(
        PaymentSync $row,
        bool $fallbackEmail = true,
        bool $linkCodeOnEmailMatch = true,
        bool $dryRun = false,
        ?int $forceEnterpriseId = null,
        ?int $forceInvoiceId = null,
        array $forceInvoiceIds = [],
        ?int $forceTypeId = null,
        ?string $remarksOverride = null,
    ): ?Payment {
        if (strtolower((string) $row->provider) !== 'mercadopago')
        {
            return null;
        }

        $status = strtolower((string) $row->status);
        if ($status !== 'approved')
        {
            return null;
        }

        $netCents = (int) $row->amount_net_cents;
        if ($netCents <= 0)
        {
            return null;
        }

        $currency = strtoupper((string) $row->currency);
        $amountMajor = $this->majorAmountFromCents($netCents, $currency);

        if ($forceEnterpriseId !== null)
        {
            $enterpriseId = $forceEnterpriseId;
            if ($linkCodeOnEmailMatch && ! $dryRun)
            {
                $this->linkPayerCodeToEnterprise($row, $enterpriseId);
            }
        } else
        {
            [$enterpriseId] = $this->resolveEnterpriseId(
                $row,
                $fallbackEmail,
                $linkCodeOnEmailMatch,
                $dryRun,
            );
        }

        if (! $enterpriseId)
        {
            return null;
        }

        $date = $this->resolvePaymentDate($row);
        $forcedInvoiceIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $forceInvoiceIds !== []
                ? $forceInvoiceIds
                : ($forceInvoiceId !== null ? [$forceInvoiceId] : []),
        ))));

        $remarks = $this->resolveRemarks($row, $remarksOverride);

        if ($dryRun)
        {
            return null;
        }

        $accountId = $this->ensureMercadoPagoPaymentAccount($row->team_id);
        $typeId = $forceTypeId ?? $this->resolveMercadoPagoPaymentTypeId();
        if ($accountId === null || $typeId === null)
        {
            return null;
        }

        if (count($forcedInvoiceIds) > 1)
        {
            $selectedInvoices = Invoice::withoutGlobalScopes()
                ->where('team_id', $row->team_id)
                ->where('enterprise_id', $enterpriseId)
                ->whereIn('id', $forcedInvoiceIds)
                ->get();

            $allPaidStripe = $selectedInvoices->count() === count($forcedInvoiceIds)
                && $selectedInvoices->every(function (Invoice $invoice): bool
                {
                    return strtolower((string) $invoice->source_provider) === 'stripe'
                        && (float) $invoice->balance <= 0
                        && str_starts_with(trim((string) $invoice->source_reference_id), 'in_');
                });

            if ($allPaidStripe)
            {
                return $this->importSplitAcrossPaidUnlinkedInvoices(
                    $row,
                    $enterpriseId,
                    $amountMajor,
                    $date,
                    $remarks,
                    $accountId,
                    $typeId,
                    $forcedInvoiceIds,
                );
            }

            return $this->importSplitAcrossInvoices(
                $row,
                $enterpriseId,
                $amountMajor,
                $date,
                $remarks,
                $accountId,
                $typeId,
                $forcedInvoiceIds,
            );
        }

        $invoiceId = $forcedInvoiceIds[0]
            ?? $this->resolveInvoiceId($row, $enterpriseId, $amountMajor, $date);

        $existing = Payment::withoutGlobalScopes()
            ->where('team_id', $row->team_id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', $row->external_id)
            ->first();

        $payload = [
            'enterprise_id' => $enterpriseId,
            'transaction_type' => TransactionType::INCOME,
            'date' => $date,
            'invoice_id' => $invoiceId,
            'account_id' => $accountId,
            'type_id' => $typeId,
            'amount' => $amountMajor,
            'remarks' => $remarks,
            'status' => 2,
            'source_provider' => 'mercadopago',
            'source_reference_id' => $row->external_id,
            'source_synced_at' => $row->last_synced_at ?? now(),
        ];

        if ($existing)
        {
            if ($existing->invoice_id !== null)
            {
                unset($payload['invoice_id']);
            }

            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        $payment = Payment::withoutGlobalScopes()->create(array_merge(
            $payload,
            ['team_id' => $row->team_id],
        ));

        $this->finalizeLinkedInvoice($payment);

        return $payment;
    }

    public function isAlreadyImported(PaymentSync $row): bool
    {
        $externalId = (string) $row->external_id;

        return Payment::withoutGlobalScopes()
            ->where('team_id', $row->team_id)
            ->where('source_provider', 'mercadopago')
            ->where(function ($query) use ($externalId): void
            {
                $query->where('source_reference_id', $externalId)
                    ->orWhere('source_reference_id', 'like', $externalId.':%');
            })
            ->exists();
    }

    /**
     * @param  list<int>  $invoiceIds
     */
    private function importSplitAcrossInvoices(
        PaymentSync $row,
        int $enterpriseId,
        float $paymentAmount,
        string $date,
        string $remarks,
        int $accountId,
        int $typeId,
        array $invoiceIds,
    ): ?Payment {
        $invoices = Invoice::withoutGlobalScopes()
            ->where('team_id', $row->team_id)
            ->where('enterprise_id', $enterpriseId)
            ->where('operation', 'sell')
            ->where('balance', '>', 0)
            ->whereIn('id', $invoiceIds)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($invoices->count() !== count($invoiceIds))
        {
            return null;
        }

        $sum = round((float) $invoices->sum(fn (Invoice $invoice) => (float) $invoice->balance), 2);
        if (abs($sum - round($paymentAmount, 2)) > 0.05)
        {
            return null;
        }

        $first = null;
        foreach ($invoices as $invoice)
        {
            $amount = round((float) $invoice->balance, 2);
            $sourceReferenceId = $row->external_id.':'.$invoice->id;

            $payment = Payment::withoutGlobalScopes()->updateOrCreate(
                [
                    'team_id' => $row->team_id,
                    'source_provider' => 'mercadopago',
                    'source_reference_id' => $sourceReferenceId,
                ],
                [
                    'enterprise_id' => $enterpriseId,
                    'transaction_type' => TransactionType::INCOME,
                    'date' => $date,
                    'invoice_id' => $invoice->id,
                    'account_id' => $accountId,
                    'type_id' => $typeId,
                    'amount' => $amount,
                    'remarks' => Str::limit($remarks.' · '.$invoice->number, 500),
                    'status' => 2,
                    'source_synced_at' => $row->last_synced_at ?? now(),
                ],
            );

            if ($payment->wasRecentlyCreated)
            {
                $this->finalizeLinkedInvoice($payment);
            }

            $first ??= $payment;
        }

        return $first;
    }

    /**
     * One Mercado Pago transfer covering several already-paid Stripe invoices (metadata backfill).
     *
     * @param  list<int>  $invoiceIds
     */
    private function importSplitAcrossPaidUnlinkedInvoices(
        PaymentSync $row,
        int $enterpriseId,
        float $paymentAmount,
        string $date,
        string $remarks,
        int $accountId,
        int $typeId,
        array $invoiceIds,
    ): ?Payment {
        $invoices = Invoice::withoutGlobalScopes()
            ->where('team_id', $row->team_id)
            ->where('enterprise_id', $enterpriseId)
            ->where('operation', 'sell')
            ->whereIn('id', $invoiceIds)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($invoices->count() !== count($invoiceIds))
        {
            return null;
        }

        $sum = round((float) $invoices->sum(fn (Invoice $invoice) => (float) $invoice->total_amount), 2);
        if (abs($sum - round($paymentAmount, 2)) > 0.05)
        {
            return null;
        }

        $first = null;
        foreach ($invoices as $invoice)
        {
            $amount = round((float) $invoice->total_amount, 2);
            $sourceReferenceId = $row->external_id.':'.$invoice->id;

            $payment = $this->adoptExistingPaymentForInvoice(
                $row,
                $invoice,
                $enterpriseId,
                $amount,
                $date,
                $remarks,
                $accountId,
                $typeId,
                $sourceReferenceId,
            );

            if (! $payment instanceof Payment)
            {
                $payment = Payment::withoutGlobalScopes()->updateOrCreate(
                    [
                        'team_id' => $row->team_id,
                        'source_provider' => 'mercadopago',
                        'source_reference_id' => $sourceReferenceId,
                    ],
                    [
                        'enterprise_id' => $enterpriseId,
                        'transaction_type' => TransactionType::INCOME,
                        'date' => $date,
                        'invoice_id' => $invoice->id,
                        'account_id' => $accountId,
                        'type_id' => $typeId,
                        'amount' => $amount,
                        'remarks' => Str::limit($remarks.' · '.$invoice->number, 500),
                        'status' => 2,
                        'source_synced_at' => $row->last_synced_at ?? now(),
                    ],
                );

                if ($payment->wasRecentlyCreated)
                {
                    $this->finalizeLinkedInvoice($payment);
                }
            }

            $first ??= $payment;
        }

        return $first;
    }

    /**
     * Reuse a payment already posted on the invoice (do not create a duplicate).
     */
    private function adoptExistingPaymentForInvoice(
        PaymentSync $row,
        Invoice $invoice,
        int $enterpriseId,
        float $amount,
        string $date,
        string $remarks,
        int $accountId,
        int $typeId,
        string $sourceReferenceId,
    ): ?Payment {
        $existing = Payment::withoutGlobalScopes()
            ->where('team_id', $row->team_id)
            ->where('invoice_id', $invoice->id)
            ->where('status', '!=', 0)
            ->orderByRaw("CASE
                WHEN source_provider = 'mercadopago' AND source_reference_id = ? THEN 0
                WHEN source_provider = 'mercadopago' AND source_reference_id = ? THEN 1
                WHEN source_provider = 'mercadopago' THEN 2
                ELSE 3
            END", [$sourceReferenceId, $row->external_id])
            ->orderBy('id')
            ->first();

        if (! $existing instanceof Payment)
        {
            return null;
        }

        $existing->fill([
            'enterprise_id' => $enterpriseId,
            'transaction_type' => TransactionType::INCOME,
            'date' => $existing->date ?: $date,
            'account_id' => $existing->account_id ?: $accountId,
            'type_id' => $existing->type_id ?: $typeId,
            'amount' => ((float) $existing->amount) > 0 ? $existing->amount : $amount,
            'remarks' => filled($existing->remarks)
                ? $existing->remarks
                : Str::limit($remarks.' · '.$invoice->number, 500),
            'status' => 2,
            'source_provider' => 'mercadopago',
            'source_reference_id' => $sourceReferenceId,
            'source_synced_at' => $row->last_synced_at ?? now(),
        ]);
        $existing->save();

        return $existing;
    }

    private function finalizeLinkedInvoice(Payment $payment): void
    {
        $this->applyPaymentToLocalInvoice($payment);

        if ($this->stripeOutOfBandPaymentService->markPaidFromPayment($payment))
        {
            return;
        }

        $this->stripeOutOfBandPaymentService->linkMetadataFromPayment($payment);
    }

    private function applyPaymentToLocalInvoice(Payment $payment): void
    {
        if (! $payment->invoice_id)
        {
            return;
        }

        $invoice = Invoice::withoutGlobalScopes()->whereKey($payment->invoice_id)->first();
        if (! $invoice instanceof Invoice)
        {
            return;
        }

        $amount = round((float) $payment->amount, 2);
        $balance = round((float) $invoice->balance, 2);
        if ($amount <= 0 || $balance <= 0)
        {
            return;
        }

        $applied = min($amount, $balance);
        $invoice->balance = max(0, round($balance - $applied, 2));
        if ((float) $invoice->balance <= 0)
        {
            $invoice->status = 2;
        }
        $invoice->save();
    }

    private function resolveRemarks(
        PaymentSync $row,
        ?string $remarksOverride,
    ): string {
        $parts = [];

        $identificationCode = $row->identificationCode();
        if ($identificationCode !== null)
        {
            $parts[] = 'Ref: '.$identificationCode;
        }

        if ($remarksOverride !== null)
        {
            $trimmed = trim($remarksOverride);
            if ($trimmed !== '')
            {
                $parts[] = $trimmed;
            }
        } else
        {
            $description = trim((string) ($row->description ?? ''));
            if ($description !== '' && ! in_array(mb_strtolower($description), ['bank transfer', 'transferencia'], true))
            {
                $parts[] = $description;
            } elseif ($identificationCode === null)
            {
                $parts[] = 'Mercado Pago '.$row->external_id;
            }
        }

        if ($parts === [])
        {
            return 'Mercado Pago '.$row->external_id;
        }

        return Str::limit(implode(' · ', $parts), 500);
    }

    private function majorAmountFromCents(int $cents, string $currency): float
    {
        $zeroDecimal = ['CLP', 'UYU', 'PYG'];
        $divisor = in_array($currency, $zeroDecimal, true) ? 1 : 100;
        $value = $divisor === 1 ? (float) $cents : round($cents / 100, 2);

        return max(0.0, $value);
    }

    private function ensureMercadoPagoPaymentAccount(int $teamId): ?int
    {
        $account = PaymentAccount::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'code' => 'mp',
            ],
            [
                'name' => 'Mercado Pago',
                'symbol' => null,
                'currency_id' => null,
                'status' => 1,
            ],
        );

        return (int) $account->id;
    }

    private function resolveMercadoPagoPaymentTypeId(): ?int
    {
        $id = PaymentType::query()->where('name', 'MercadoPago')->value('id');
        if ($id !== null)
        {
            return (int) $id;
        }

        $id = PaymentType::query()->where('name', 'Mercado Pago')->value('id');
        if ($id !== null)
        {
            return (int) $id;
        }

        $fallback = PaymentType::query()->orderBy('id')->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    private function resolveInvoiceId(PaymentSync $row, int $enterpriseId, float $amount, string $paymentDate): ?int
    {
        $ref = trim((string) ($row->invoice_external_id ?? ''));
        if ($ref === '')
        {
            $ref = trim((string) data_get($row->raw_payload, 'external_reference', ''));
        }

        if ($ref !== '' && ! $this->isGenericPaymentLabel($ref))
        {
            $byReference = Invoice::withoutGlobalScopes()
                ->where('team_id', $row->team_id)
                ->where('operation', 'sell')
                ->where(function ($query) use ($ref): void
                {
                    $query->where('number', $ref)
                        ->orWhere('source_reference_id', $ref);

                    if (ctype_digit($ref))
                    {
                        $query->orWhere('id', (int) $ref);
                    }
                })
                ->orderByDesc('id')
                ->first();

            if ($byReference)
            {
                return (int) $byReference->id;
            }
        }

        $amountQuery = Invoice::withoutGlobalScopes()
            ->where('team_id', $row->team_id)
            ->where('enterprise_id', $enterpriseId)
            ->where('operation', 'sell')
            ->where('balance', '>', 0)
            ->where(function ($query) use ($amount): void
            {
                $query->whereBetween('total_amount', [$amount - 0.01, $amount + 0.01])
                    ->orWhereBetween('gross_amount', [$amount - 0.01, $amount + 0.01]);
            });

        $matchCount = (clone $amountQuery)->count();
        if ($matchCount === 1)
        {
            return (int) $amountQuery->value('id');
        }

        if ($matchCount > 1)
        {
            $from = Carbon::parse($paymentDate)->subDays(45)->toDateString();
            $to = Carbon::parse($paymentDate)->addDays(15)->toDateString();
            $dated = (clone $amountQuery)->whereBetween('date', [$from, $to]);

            if ($dated->count() === 1)
            {
                return (int) $dated->value('id');
            }
        }

        return null;
    }

    private function isGenericPaymentLabel(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return in_array($normalized, [
            'bank transfer',
            'varios',
            'payment',
            'pago',
            'transferencia',
        ], true);
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function resolveEnterpriseId(
        PaymentSync $row,
        bool $fallbackEmail,
        bool $linkCodeOnEmailMatch,
        bool $dryRun,
    ): array {
        $customerId = $row->customer_id !== null ? trim((string) $row->customer_id) : '';

        if ($customerId !== '')
        {
            $enterprise = Enterprise::query()
                ->where('team_id', $row->team_id)
                ->where('type_id', 1)
                ->where('code', $customerId)
                ->first();

            if ($enterprise)
            {
                return [$enterprise->id, 'code'];
            }
        }

        $settlementName = trim((string) ($row->settlementPayerName() ?? ''));
        if ($settlementName !== '')
        {
            $normalizedPayer = mb_strtolower(preg_replace('/\s+/u', ' ', trim($settlementName)) ?? '');
            $nameMatches = Enterprise::query()
                ->where('team_id', $row->team_id)
                ->where('type_id', 1)
                ->get(['id', 'name'])
                ->filter(function (Enterprise $enterprise) use ($normalizedPayer): bool
                {
                    $normalizedEnterprise = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $enterprise->name)) ?? '');

                    return $normalizedEnterprise !== ''
                        && (
                            $normalizedEnterprise === $normalizedPayer
                            || str_contains($normalizedPayer, $normalizedEnterprise)
                            || str_contains($normalizedEnterprise, $normalizedPayer)
                        );
                })
                ->values();

            if ($nameMatches->count() === 1)
            {
                return [(int) $nameMatches->first()->id, 'settlement_name'];
            }
        }

        if (! $fallbackEmail)
        {
            return [null, 'none'];
        }

        $email = strtolower(trim((string) ($row->customer_email ?? '')));
        if ($email === '')
        {
            return [null, 'none'];
        }

        $emailMatches = Enterprise::query()
            ->where('team_id', $row->team_id)
            ->where('type_id', 1)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        if ($emailMatches->count() !== 1)
        {
            return [null, 'none'];
        }

        /** @var Enterprise $matched */
        $matched = $emailMatches->first();

        if ($linkCodeOnEmailMatch && $customerId !== '' && blank($matched->code) && ! $dryRun)
        {
            $matched->code = $customerId;
            $matched->save();
        }

        return [$matched->id, 'email'];
    }

    private function linkPayerCodeToEnterprise(PaymentSync $row, int $enterpriseId): void
    {
        $customerId = $row->customer_id !== null ? trim((string) $row->customer_id) : '';
        if ($customerId === '')
        {
            return;
        }

        $enterprise = Enterprise::query()
            ->where('team_id', $row->team_id)
            ->whereKey($enterpriseId)
            ->first();

        if (! $enterprise || filled($enterprise->code))
        {
            return;
        }

        $codeTaken = Enterprise::query()
            ->where('team_id', $row->team_id)
            ->where('code', $customerId)
            ->whereKeyNot($enterpriseId)
            ->exists();

        if ($codeTaken)
        {
            return;
        }

        $enterprise->code = $customerId;
        $enterprise->save();
    }

    private function resolvePaymentDate(PaymentSync $row): string
    {
        if ($row->charge_created_at)
        {
            return $row->charge_created_at->toDateString();
        }

        $raw = data_get($row->raw_payload, 'date_approved')
            ?? data_get($row->raw_payload, 'date_created');

        if (is_string($raw) && trim($raw) !== '')
        {
            try
            {
                return Carbon::parse($raw)->toDateString();
            } catch (\Throwable)
            {
            }
        }

        return now()->toDateString();
    }

    private function hasNonCancelledPaymentForInvoice(int $invoiceId): bool
    {
        return Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoiceId)
            ->where('status', '!=', 0)
            ->exists();
    }

    private function findMercadoPagoSyncForStripeInvoice(int $teamId, string $stripeExternalId): ?PaymentSync
    {
        foreach ($this->stripeOutOfBandPaymentReferences($teamId, $stripeExternalId) as $reference)
        {
            $sync = $this->findMercadoPagoSyncByReference($teamId, $reference);
            if ($sync instanceof PaymentSync)
            {
                return $sync;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function stripeOutOfBandPaymentReferences(int $teamId, string $stripeExternalId): array
    {
        $invoiceSync = InvoiceSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'stripe')
            ->where('external_id', $stripeExternalId)
            ->orderByDesc('id')
            ->first();

        if (! $invoiceSync)
        {
            return [];
        }

        $refs = [];
        foreach ([
            data_get($invoiceSync->raw_payload, 'metadata.payment_reference'),
            data_get($invoiceSync->raw_payload, 'metadata.mercadopago_id'),
            data_get($invoiceSync->raw_payload, 'metadata.mercadopago_payment_id'),
        ] as $value)
        {
            $trimmed = trim((string) $value);
            if ($trimmed !== '')
            {
                $refs[] = $trimmed;
            }
        }

        return array_values(array_unique($refs));
    }

    private function stripeOutOfBandPaymentReference(int $teamId, string $stripeExternalId): ?string
    {
        $refs = $this->stripeOutOfBandPaymentReferences($teamId, $stripeExternalId);

        return $refs[0] ?? null;
    }

    private function findMercadoPagoSyncByReference(int $teamId, string $paymentReference): ?PaymentSync
    {
        $candidates = PaymentSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'mercadopago')
            ->where('status', 'approved')
            ->where(function ($query) use ($paymentReference): void
            {
                $query->where('external_id', $paymentReference)
                    ->orWhereRaw(
                        "TRIM(COALESCE(raw_payload->'transaction_details'->>'transaction_id', '')) = ?",
                        [$paymentReference],
                    )
                    ->orWhereRaw(
                        "TRIM(COALESCE(raw_payload->'point_of_interaction'->'transaction_data'->>'e2e_id', '')) = ?",
                        [$paymentReference],
                    )
                    ->orWhereRaw(
                        "TRIM(COALESCE(raw_payload->'point_of_interaction'->'transaction_data'->>'transaction_id', '')) = ?",
                        [$paymentReference],
                    );
            })
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $candidate)
        {
            if (! $candidate instanceof PaymentSync)
            {
                continue;
            }

            if (in_array($paymentReference, $candidate->stripeMatchReferences(), true))
            {
                return $candidate;
            }
        }

        return null;
    }
}
