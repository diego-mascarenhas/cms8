<?php

namespace App\Services\Billing;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Services\Finance\ServiceCategoryOptionsService;
use RuntimeException;

class ServiceSyncImporter
{
    public function __construct(
        private readonly ServiceCategoryOptionsService $serviceCategoryOptionsService,
    ) {}

    public function findLinkedService(ServiceSync $sync): ?Service
    {
        return Service::withoutGlobalScopes()
            ->where('subscription_id', $sync->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * @throws RuntimeException when enterprise cannot be resolved or service already exists
     */
    public function createServiceFromSync(
        ServiceSync $sync,
        ?int $categoryId = null,
        bool $fallbackEmail = true,
        bool $linkCodeOnEmailMatch = true,
        bool $assignDefaultCategory = true,
    ): Service {
        $existing = $this->findLinkedService($sync);
        if ($existing)
        {
            throw new RuntimeException(__('A service is already linked to this subscription.'));
        }

        [$enterpriseId] = $this->resolveEnterpriseId(
            $sync,
            $fallbackEmail,
            $linkCodeOnEmailMatch,
            dryRun: false,
        );

        if (! $enterpriseId)
        {
            throw new RuntimeException(__('No client is linked to this subscription. Link a client first.'));
        }

        $resolvedCategoryId = $assignDefaultCategory
            ? $this->resolveCategoryId((int) $sync->team_id, $categoryId)
            : $this->resolveOptionalCategoryId((int) $sync->team_id, $categoryId);

        $description = trim((string) ($sync->plan_name ?? ''));
        if ($description === '')
        {
            $description = 'Sync '.(string) ($sync->provider ?? 'stripe').' '.(string) $sync->stripe_id;
        }

        return Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterpriseId,
            'subscription_id' => $sync->id,
            'category_id' => $resolvedCategoryId,
            'operation' => 'sell',
            'description' => $description,
            'data' => is_array($sync->data) ? $sync->data : [],
            'currency_id' => $this->resolveCurrencyId((string) ($sync->price_currency ?? '')),
            'price' => $sync->amount_total ?? $sync->unit_amount,
            'discount' => 0,
            'frequency' => 1,
            'next_billing' => $sync->current_period_end,
            'expires_at' => $sync->current_period_end,
            'responsible_id' => null,
            'status' => $this->mapServiceStatus((string) ($sync->status ?? '')),
        ]);
    }

    public function updateServiceCategory(ServiceSync $sync, ?int $categoryId): Service
    {
        $service = $this->findLinkedService($sync);
        if (! $service)
        {
            throw new RuntimeException(__('No service is linked to this subscription.'));
        }

        $teamId = (int) $sync->team_id;
        if ($categoryId !== null && ! $this->serviceCategoryOptionsService->belongsToTeamServices($teamId, $categoryId))
        {
            throw new RuntimeException(__('The selected category is invalid.'));
        }

        $service->forceFill([
            'category_id' => $categoryId,
        ])->save();

        return $service->fresh(['category']) ?? $service;
    }

    public function resolveCategoryIdForInvoiceItem(int $teamId, ?string $stripeSubscriptionId): ?int
    {
        if (! filled($stripeSubscriptionId))
        {
            return null;
        }

        $sync = ServiceSync::query()
            ->where('team_id', $teamId)
            ->where('stripe_id', $stripeSubscriptionId)
            ->first();

        if (! $sync)
        {
            return null;
        }

        $service = $this->findLinkedService($sync);

        return $service?->category_id ? (int) $service->category_id : null;
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    public function resolveEnterpriseId(
        ServiceSync $row,
        bool $fallbackEmail,
        bool $linkCodeOnEmailMatch,
        bool $dryRun,
    ): array {
        $customerId = trim((string) ($row->customer_id ?? ''));
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

        if ($linkCodeOnEmailMatch && $customerId !== '' && blank($matched->code))
        {
            if (! $dryRun)
            {
                $matched->code = $customerId;
                $matched->save();
            }
        }

        return [$matched->id, 'email'];
    }

    public function resolveCurrencyId(string $code): ?int
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '')
        {
            return null;
        }

        return Currency::query()->where('code', $normalized)->value('id');
    }

    public function mapServiceStatus(string $providerStatus): int
    {
        return match (strtolower(trim($providerStatus)))
        {
            'active', 'trialing' => 4,
            'past_due', 'unpaid', 'incomplete' => 2,
            'canceled', 'incomplete_expired', 'paused' => 1,
            default => 1,
        };
    }

    private function resolveCategoryId(int $teamId, ?int $categoryId): int
    {
        $optional = $this->resolveOptionalCategoryId($teamId, $categoryId);
        if ($optional !== null)
        {
            return $optional;
        }

        $moduleId = Module::query()->where('key', 'services')->value('id');
        $default = Category::query()
            ->where('status', '>', 0)
            ->where(function ($query) use ($teamId)
            {
                $query->whereNull('team_id')->orWhere('team_id', $teamId);
            })
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->orderBy('id')
            ->value('id');

        if ($default === null)
        {
            throw new RuntimeException(__('No service categories found. Create a category first.'));
        }

        return (int) $default;
    }

    private function resolveOptionalCategoryId(int $teamId, ?int $categoryId): ?int
    {
        if ($categoryId === null)
        {
            return null;
        }

        if (! $this->serviceCategoryOptionsService->belongsToTeamServices($teamId, $categoryId))
        {
            throw new RuntimeException(__('The selected category is invalid.'));
        }

        return $categoryId;
    }
}
