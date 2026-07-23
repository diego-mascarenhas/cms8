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
    ) {}

    /**
     * When a Stripe invoice was marked paid out of band with a Mercado Pago reference,
     * create the matching Humano payment if it is still missing.
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

        if ($this->hasNonCancelledPaymentForInvoice((int) $invoice->id))
        {
            return null;
        }

        $paymentReference = $this->stripeOutOfBandPaymentReference(
            (int) $invoice->team_id,
            $stripeExternalId,
        );
        if ($paymentReference === null)
        {
            return null;
        }

        $sync = $this->findMercadoPagoSyncByReference((int) $invoice->team_id, $paymentReference);
        if (! $sync instanceof PaymentSync || $this->isAlreadyImported($sync))
        {
            return null;
        }

        return $this->importFromPaymentSync(
            $sync,
            fallbackEmail: false,
            linkCodeOnEmailMatch: false,
            dryRun: false,
            forceEnterpriseId: (int) $invoice->enterprise_id,
            forceInvoiceIds: [(int) $invoice->id],
        );
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

    private function finalizeLinkedInvoice(Payment $payment): void
    {
        $this->applyPaymentToLocalInvoice($payment);
        $this->stripeOutOfBandPaymentService->markPaidFromPayment($payment);
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

    private function stripeOutOfBandPaymentReference(int $teamId, string $stripeExternalId): ?string
    {
        $invoiceSync = InvoiceSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'stripe')
            ->where('external_id', $stripeExternalId)
            ->orderByDesc('id')
            ->first();

        if (! $invoiceSync)
        {
            return null;
        }

        $reference = trim((string) data_get($invoiceSync->raw_payload, 'metadata.payment_reference', ''));

        return $reference !== '' ? $reference : null;
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
