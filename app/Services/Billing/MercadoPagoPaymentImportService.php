<?php

namespace App\Services\Billing;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentSync;
use App\Models\PaymentType;
use Illuminate\Support\Str;

class MercadoPagoPaymentImportService
{
    public function importFromPaymentSync(
        PaymentSync $row,
        bool $fallbackEmail = true,
        bool $linkCodeOnEmailMatch = true,
        bool $dryRun = false,
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

        $date = $this->resolvePaymentDate($row);
        $description = (string) ($row->description ?? '');
        $remarks = trim($description) !== ''
            ? Str::limit($description, 500)
            : 'Mercado Pago '.$row->external_id;

        if ($dryRun)
        {
            return null;
        }

        $accountId = $this->ensureMercadoPagoPaymentAccount($row->team_id);
        $typeId = $this->resolveMercadoPagoPaymentTypeId();
        if ($accountId === null || $typeId === null)
        {
            return null;
        }

        $existing = Payment::withoutGlobalScopes()
            ->where('team_id', $row->team_id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', $row->external_id)
            ->first();

        $payload = [
            'enterprise_id' => $enterpriseId,
            'transaction_type' => TransactionType::INCOME,
            'date' => $date,
            'invoice_id' => null,
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
            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        return Payment::withoutGlobalScopes()->create(array_merge(
            $payload,
            ['team_id' => $row->team_id],
        ));
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
                'code' => 'mercadopago',
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
                return \Carbon\Carbon::parse($raw)->toDateString();
            } catch (\Throwable)
            {
            }
        }

        return now()->toDateString();
    }
}
