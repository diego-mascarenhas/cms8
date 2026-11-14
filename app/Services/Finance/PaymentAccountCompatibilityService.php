<?php

namespace App\Services\Finance;

use App\Models\PaymentAccount;
use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PaymentAccountCompatibilityService
{
    /** @var array<string, list<int>> */
    public const DEFAULT_TYPES_BY_ACCOUNT_CODE = [
        'CASH' => [1],
        'BANK_EUR' => [2, 11],
        'BANK_USD' => [2],
        'STRIPE_EUR' => [6, 8],
        'STRIPE_USD' => [6, 8],
        'PAYPAL_EUR' => [6, 7],
        'PAYPAL_USD' => [6, 7],
        'WISE_EUR' => [9],
        'WISE_USD' => [9],
        'MPAGO_ARS' => [2, 12],
        'CUENTICA' => [13],
        'PAYPAL' => [6, 7],
        'STRIPE' => [6, 8],
        'WISE' => [9],
        'MERCADOPAGO' => [2, 12],
        'BANK' => [2, 11],
        'EUR' => [2, 11],
        'USD' => [2],
        'MAIN' => [2],
    ];

    /**
     * @return list<int>
     */
    public function acceptedPaymentTypeIds(PaymentAccount $account): array
    {
        $account->loadMissing('paymentTypes');

        $configuredIds = $account->paymentTypes
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($configuredIds !== [])
        {
            return $configuredIds;
        }

        return $this->activePaymentTypeIds();
    }

    /**
     * @return list<int>
     */
    public function activePaymentTypeIds(): array
    {
        $query = PaymentType::query()->orderBy('name');

        if (Schema::hasColumn('payment_types', 'is_active'))
        {
            $query->where('is_active', true);
        }

        return $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function accountAcceptsType(PaymentAccount $account, int $paymentTypeId): bool
    {
        return in_array($paymentTypeId, $this->acceptedPaymentTypeIds($account), true);
    }

    /**
     * Prefer last payment account, then same-currency account, then type compatibility.
     *
     * @param  EloquentCollection<int, PaymentAccount>  $accounts
     * @param  EloquentCollection<int, PaymentType>|null  $paymentTypes
     * @return array{0: int|null, 1: int|null, 2: string}
     */
    public function resolveDefaults(
        EloquentCollection $accounts,
        ?EloquentCollection $paymentTypes = null,
        ?int $preferredTypeId = null,
        ?int $preferredAccountId = null,
        ?int $currencyId = null,
    ): array {
        if ($accounts->isEmpty())
        {
            return [null, null, 'none'];
        }

        $paymentTypes ??= PaymentType::query()->orderBy('name')->get();

        if ($preferredAccountId !== null)
        {
            $preferredAccount = $accounts->firstWhere('id', $preferredAccountId);

            if ($preferredAccount instanceof PaymentAccount)
            {
                return [
                    (int) $preferredAccount->id,
                    $this->defaultTypeForAccount($preferredAccount, $paymentTypes, $preferredTypeId),
                    'last_payment',
                ];
            }
        }

        if ($currencyId !== null)
        {
            $currencyAccount = $accounts->first(
                fn (PaymentAccount $account): bool => (int) $account->currency_id === (int) $currencyId,
            );

            if ($currencyAccount instanceof PaymentAccount)
            {
                return [
                    (int) $currencyAccount->id,
                    $this->defaultTypeForAccount($currencyAccount, $paymentTypes, $preferredTypeId),
                    'currency',
                ];
            }
        }

        foreach ($accounts as $account)
        {
            $acceptedTypeIds = $this->acceptedPaymentTypeIds($account);

            if ($preferredTypeId !== null && in_array($preferredTypeId, $acceptedTypeIds, true))
            {
                return [(int) $account->id, $preferredTypeId, 'fallback'];
            }
        }

        foreach ($accounts as $account)
        {
            $acceptedTypeIds = $this->acceptedPaymentTypeIds($account);

            if ($acceptedTypeIds === [])
            {
                continue;
            }

            $firstTypeId = $acceptedTypeIds[0];

            if ($paymentTypes->contains('id', $firstTypeId))
            {
                return [(int) $account->id, $firstTypeId, 'fallback'];
            }
        }

        return [
            (int) $accounts->first()->id,
            $preferredTypeId ?? (int) ($paymentTypes->first()?->id),
            'fallback',
        ];
    }

    /**
     * @param  EloquentCollection<int, PaymentType>  $paymentTypes
     */
    private function defaultTypeForAccount(
        PaymentAccount $account,
        EloquentCollection $paymentTypes,
        ?int $preferredTypeId,
    ): ?int {
        $acceptedTypeIds = $this->acceptedPaymentTypeIds($account);

        if ($preferredTypeId !== null && in_array($preferredTypeId, $acceptedTypeIds, true))
        {
            return $preferredTypeId;
        }

        foreach ($acceptedTypeIds as $typeId)
        {
            if ($paymentTypes->contains('id', $typeId))
            {
                return (int) $typeId;
            }
        }

        return $preferredTypeId ?? (int) ($paymentTypes->first()?->id);
    }

    /**
     * @param  Collection<int, PaymentAccount>|EloquentCollection<int, PaymentAccount>  $accounts
     * @return Collection<int, PaymentAccount>
     */
    public function filterAccountsForSelection(
        Collection|EloquentCollection $accounts,
        ?int $currencyId,
        ?int $paymentTypeId,
    ): Collection {
        return $accounts
            ->when($currencyId, fn (Collection $items) => $items->filter(
                fn (PaymentAccount $account) => (int) $account->currency_id === (int) $currencyId,
            ))
            ->when($paymentTypeId, fn (Collection $items) => $items->filter(
                fn (PaymentAccount $account) => $this->accountAcceptsType($account, $paymentTypeId),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, PaymentType>|EloquentCollection<int, PaymentType>  $paymentTypes
     * @return Collection<int, PaymentType>
     */
    public function filterPaymentTypesForAccount(
        Collection|EloquentCollection $paymentTypes,
        ?PaymentAccount $account,
    ): Collection {
        if ($account === null)
        {
            return $paymentTypes->values();
        }

        $acceptedTypeIds = $this->acceptedPaymentTypeIds($account);

        return $paymentTypes
            ->filter(fn (PaymentType $type) => in_array((int) $type->id, $acceptedTypeIds, true))
            ->values();
    }

    public function syncConfiguredPaymentTypes(PaymentAccount $account, array $paymentTypeIds): void
    {
        $account->paymentTypes()->sync(
            collect($paymentTypeIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        );
    }

    public function syncDefaultPaymentTypesForAccount(PaymentAccount $account): void
    {
        if ($account->paymentTypes()->exists())
        {
            return;
        }

        $code = strtoupper((string) $account->code);
        $defaultTypeIds = self::DEFAULT_TYPES_BY_ACCOUNT_CODE[$code] ?? null;

        if ($defaultTypeIds === null)
        {
            foreach (self::DEFAULT_TYPES_BY_ACCOUNT_CODE as $prefix => $typeIds)
            {
                if (str_starts_with($code, $prefix.'_') || str_starts_with($code, $prefix))
                {
                    $defaultTypeIds = $typeIds;
                    break;
                }
            }
        }

        if ($defaultTypeIds === null)
        {
            $defaultTypeIds = self::DEFAULT_TYPES_BY_ACCOUNT_CODE['BANK'];
        }

        $existingTypeIds = PaymentType::query()
            ->whereIn('id', $defaultTypeIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($existingTypeIds !== [])
        {
            $this->syncConfiguredPaymentTypes($account, $existingTypeIds);
        }
    }

    /**
     * @return array<int, array{id: int, name: string, currency_id: int|null, currency_code: string, payment_type_ids: list<int>}>
     */
    public function mapAccountsForFrontend(EloquentCollection $accounts): array
    {
        return $accounts->map(function (PaymentAccount $account): array
        {
            return [
                'id' => (int) $account->id,
                'name' => (string) $account->name,
                'currency_id' => $account->currency_id ? (int) $account->currency_id : null,
                'currency_code' => strtoupper((string) ($account->currency->code ?? 'USD')),
                'payment_type_ids' => $this->acceptedPaymentTypeIds($account),
            ];
        })->values()->all();
    }
}
