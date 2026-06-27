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
        'PAYPAL' => [7],
        'STRIPE' => [8],
        'WISE' => [9],
        'BIZUM' => [11],
        'MERCADOPAGO' => [12],
        'CUENTICA' => [13],
        'EUR' => [1, 2, 3, 4, 5, 6, 9, 11, 13],
        'USD' => [1, 2, 3, 4, 5, 6, 9, 11, 12, 13],
        'BANK' => [2, 3, 4, 5, 6, 9],
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
     * @param  EloquentCollection<int, PaymentAccount>  $accounts
     * @param  EloquentCollection<int, PaymentType>|null  $paymentTypes
     * @return array{0: int|null, 1: int|null}
     */
    public function resolveDefaults(
        EloquentCollection $accounts,
        ?EloquentCollection $paymentTypes = null,
        ?int $preferredTypeId = null,
    ): array {
        if ($accounts->isEmpty())
        {
            return [null, null];
        }

        $paymentTypes ??= PaymentType::query()->orderBy('name')->get();

        foreach ($accounts as $account)
        {
            $acceptedTypeIds = $this->acceptedPaymentTypeIds($account);

            if ($preferredTypeId !== null && in_array($preferredTypeId, $acceptedTypeIds, true))
            {
                return [(int) $account->id, $preferredTypeId];
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
                return [(int) $account->id, $firstTypeId];
            }
        }

        return [(int) $accounts->first()->id, $preferredTypeId ?? (int) ($paymentTypes->first()?->id)];
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
