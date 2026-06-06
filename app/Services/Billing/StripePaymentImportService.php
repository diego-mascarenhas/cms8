<?php

namespace App\Services\Billing;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentSync;
use App\Models\PaymentType;
use Illuminate\Support\Str;

class StripePaymentImportService
{
    public function importFromPaymentSync(
        PaymentSync $row,
        bool $fallbackEmail = true,
        bool $linkCodeOnEmailMatch = true,
        bool $dryRun = false,
    ): ?Payment {
        $status = strtolower((string) $row->status);
        if ($status !== 'succeeded')
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

        [$enterpriseId] = $this->resolveEnterpriseId(
            $row,
            $fallbackEmail,
            $linkCodeOnEmailMatch,
            $dryRun,
        );

        if (! $enterpriseId)
        {
            return null;
        }

        $invoiceId = $this->resolveInvoiceId($row->team_id, $row->invoice_external_id);
        $date = $this->resolvePaymentDate($row);

        $description = (string) ($row->description ?? '');
        $remarks = trim($description) !== ''
            ? Str::limit($description, 500)
            : 'Stripe '.$row->external_id;

        if ($dryRun)
        {
            return null;
        }

        $accountId = $this->ensureStripePaymentAccount($row->team_id);
        $typeId = $this->resolveStripePaymentTypeId();
        if ($accountId === null || $typeId === null)
        {
            return null;
        }

        $existing = Payment::withoutGlobalScopes()
            ->where('team_id', $row->team_id)
            ->where('source_provider', 'stripe')
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
            'source_provider' => 'stripe',
            'source_reference_id' => $row->external_id,
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
            ['team_id' => $row->team_id],
        ));
    }

    public function createPaymentFromPaidInvoice(
        Invoice $invoice,
        float $amount,
        string $date,
        ?string $sourceReferenceId = null,
        ?string $remarks = null,
        bool $dryRun = false,
    ): ?Payment {
        if ($amount <= 0 || ! filled($invoice->enterprise_id))
        {
            return null;
        }

        $sourceReferenceId ??= 'stripe-invoice:'.$invoice->source_reference_id;

        if ($dryRun)
        {
            return null;
        }

        $existing = Payment::withoutGlobalScopes()
            ->where('team_id', $invoice->team_id)
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $sourceReferenceId)
            ->first();

        if ($existing)
        {
            if ($existing->invoice_id === null && $invoice->id)
            {
                $existing->invoice_id = $invoice->id;
                $existing->save();
            }

            return $existing;
        }

        $accountId = $this->ensureStripePaymentAccount($invoice->team_id);
        $typeId = $this->resolveStripePaymentTypeId();
        if ($accountId === null || $typeId === null)
        {
            return null;
        }

        return Payment::withoutGlobalScopes()->create([
            'team_id' => $invoice->team_id,
            'enterprise_id' => $invoice->enterprise_id,
            'transaction_type' => TransactionType::INCOME,
            'date' => $date,
            'invoice_id' => $invoice->id,
            'account_id' => $accountId,
            'type_id' => $typeId,
            'amount' => round($amount, 2),
            'remarks' => $remarks ?? ('Stripe invoice '.$invoice->number),
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => $sourceReferenceId,
            'source_synced_at' => now(),
        ]);
    }

    public function invoiceHasApprovedPayments(Invoice $invoice): bool
    {
        return Payment::withoutGlobalScopes()
            ->where('team_id', $invoice->team_id)
            ->where('status', '!=', 0)
            ->where(function ($query) use ($invoice): void
            {
                $query->where('invoice_id', $invoice->id);

                if ($invoice->source_provider === 'stripe' && filled($invoice->source_reference_id))
                {
                    $query->orWhereIn('source_reference_id', PaymentSync::query()
                        ->where('team_id', $invoice->team_id)
                        ->where('invoice_external_id', $invoice->source_reference_id)
                        ->select('external_id'));
                }
            })
            ->exists();
    }

    private function majorAmountFromCents(int $cents, string $currency): float
    {
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];
        $divisor = in_array($currency, $zeroDecimal, true) ? 1 : 100;
        $value = $divisor === 1 ? (float) $cents : round($cents / 100, 2);

        return max(0.0, $value);
    }

    private function ensureStripePaymentAccount(int $teamId): ?int
    {
        $account = PaymentAccount::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'code' => 'stripe',
            ],
            [
                'name' => 'Stripe',
                'symbol' => null,
                'currency_id' => null,
                'status' => 1,
            ],
        );

        return (int) $account->id;
    }

    private function resolveStripePaymentTypeId(): ?int
    {
        $id = PaymentType::query()->where('name', 'Stripe')->value('id');
        if ($id !== null)
        {
            return (int) $id;
        }

        $fallback = PaymentType::query()->orderBy('id')->value('id');

        return $fallback !== null ? (int) $fallback : null;
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

    private function resolveInvoiceId(int $teamId, ?string $invoiceExternalId): ?int
    {
        if ($invoiceExternalId === null || $invoiceExternalId === '' || ! str_starts_with($invoiceExternalId, 'in_'))
        {
            return null;
        }

        $id = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $invoiceExternalId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function resolvePaymentDate(PaymentSync $row): string
    {
        if ($row->charge_created_at)
        {
            return $row->charge_created_at->toDateString();
        }

        $rawCreated = data_get($row->raw_payload, 'created');
        if (is_numeric($rawCreated))
        {
            return \Carbon\Carbon::createFromTimestamp((int) $rawCreated)->toDateString();
        }

        return now()->toDateString();
    }
}
