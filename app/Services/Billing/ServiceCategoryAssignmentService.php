<?php

namespace App\Services\Billing;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\TeamUsageInvoice;
use Illuminate\Support\Arr;

class ServiceCategoryAssignmentService
{
    public const USAGE_CATEGORY_SETTING = 'finance_usage_category_id';

    public function __construct(
        private readonly ServiceSyncImporter $serviceSyncImporter,
        private readonly StripeCategoryMetadataWriter $stripeCategoryMetadataWriter,
    ) {}

    public function publish(Service $service): void
    {
        $service->loadMissing('category');
        $this->applyToInvoiceItems($service);
        $sync = $this->syncForService($service);

        if ($sync)
        {
            $this->stripeCategoryMetadataWriter->write(
                $sync,
                $service->category?->name,
            );
        }
    }

    public function categoryIdForInvoice(Invoice $invoice): ?int
    {
        $service = $this->linkedService($invoice);

        if ($service?->category_id)
        {
            return (int) $service->category_id;
        }

        if ($this->isUsageInvoice($invoice))
        {
            return $this->usageCategoryIdForTeam((int) $invoice->team_id);
        }

        return null;
    }

    public function pushInvoiceCategory(Invoice $invoice, ?int $categoryId): void
    {
        $name = null;

        if ($categoryId)
        {
            $name = Category::query()->whereKey($categoryId)->value('name');
            $name = filled($name) ? (string) $name : null;
        }

        $service = $this->linkedService($invoice);

        if ($service)
        {
            $service->forceFill(['category_id' => $categoryId])->save();
            $this->applyToInvoiceItems($service);
            $sync = $this->syncForService($service);

            if ($sync)
            {
                $this->stripeCategoryMetadataWriter->write($sync, $name);
            }
        }

        if ($this->isUsageInvoice($invoice))
        {
            $this->rememberUsageCategory((int) $invoice->team_id, $categoryId);
            $this->applyUsageCategoryToTeamInvoices((int) $invoice->team_id, $categoryId);
        }
    }

    public function usageCategoryIdForTeam(int $teamId): ?int
    {
        $team = Team::query()->find($teamId);

        if (! $team)
        {
            return null;
        }

        $raw = $team->getSetting(self::USAGE_CATEGORY_SETTING);

        if ($raw === null || $raw === '' || ! is_numeric($raw))
        {
            return null;
        }

        $categoryId = (int) $raw;

        return $categoryId > 0 ? $categoryId : null;
    }

    public function rememberUsageCategory(int $teamId, ?int $categoryId): void
    {
        $team = Team::query()->find($teamId);

        if (! $team)
        {
            return;
        }

        $team->setSetting(self::USAGE_CATEGORY_SETTING, $categoryId, [
            'type' => 'integer',
            'group' => 'finance',
        ]);
    }

    public function applyUsageCategoryToTeamInvoices(int $teamId, ?int $categoryId): void
    {
        $externalIds = TeamUsageInvoice::query()
            ->where('team_id', $teamId)
            ->whereNotNull('stripe_invoice_id')
            ->pluck('stripe_invoice_id')
            ->filter()
            ->values();

        $usageSyncIds = InvoiceSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'stripe')
            ->get(['id', 'external_id', 'raw_payload'])
            ->filter(fn (InvoiceSync $sync): bool => $this->payloadIsUsage(is_array($sync->raw_payload) ? $sync->raw_payload : []))
            ->pluck('external_id')
            ->filter();

        $allExternalIds = $externalIds->merge($usageSyncIds)->unique()->values();

        if ($allExternalIds->isEmpty())
        {
            return;
        }

        $invoiceIds = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('source_provider', 'stripe')
            ->whereIn('source_reference_id', $allExternalIds->all())
            ->pluck('id');

        if ($invoiceIds->isEmpty())
        {
            return;
        }

        InvoiceItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->update([
                'category_id' => $categoryId,
            ]);
    }

    public function isUsageInvoice(Invoice $invoice): bool
    {
        if (! filled($invoice->source_reference_id) || (string) $invoice->source_provider !== 'stripe')
        {
            return false;
        }

        $externalId = (string) $invoice->source_reference_id;

        if (TeamUsageInvoice::query()
            ->where('team_id', $invoice->team_id)
            ->where('stripe_invoice_id', $externalId)
            ->exists())
        {
            return true;
        }

        $sync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $externalId)
            ->first();

        return $sync instanceof InvoiceSync
            && $this->payloadIsUsage(is_array($sync->raw_payload) ? $sync->raw_payload : []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function payloadIsUsage(array $payload): bool
    {
        return (string) Arr::get($payload, 'metadata.humano_usage') === '1';
    }

    public function applyToInvoiceItems(Service $service): void
    {
        $sync = $this->syncForService($service);
        if (! $sync || ! filled($sync->stripe_id))
        {
            return;
        }

        $externalIds = InvoiceSync::query()
            ->where('team_id', $sync->team_id)
            ->where('provider', 'stripe')
            ->where('stripe_subscription_id', $sync->stripe_id)
            ->pluck('external_id')
            ->filter()
            ->values();

        if ($externalIds->isEmpty())
        {
            return;
        }

        $invoiceIds = Invoice::withoutGlobalScopes()
            ->where('team_id', $sync->team_id)
            ->where('source_provider', 'stripe')
            ->whereIn('source_reference_id', $externalIds)
            ->pluck('id');

        if ($invoiceIds->isEmpty())
        {
            return;
        }

        InvoiceItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->update([
                'category_id' => $service->category_id,
            ]);
    }

    private function linkedService(Invoice $invoice): ?Service
    {
        $sync = $this->subscriptionSyncForInvoice($invoice);

        return $sync ? $this->serviceSyncImporter->findLinkedService($sync) : null;
    }

    private function subscriptionSyncForInvoice(Invoice $invoice): ?ServiceSync
    {
        if (! filled($invoice->source_reference_id))
        {
            return null;
        }

        $invoiceSync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $invoice->source_reference_id)
            ->first();

        if (! $invoiceSync || ! filled($invoiceSync->stripe_subscription_id))
        {
            return null;
        }

        return ServiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('stripe_id', $invoiceSync->stripe_subscription_id)
            ->first();
    }

    private function syncForService(Service $service): ?ServiceSync
    {
        if (! $service->subscription_id)
        {
            return null;
        }

        return ServiceSync::query()->find($service->subscription_id);
    }
}
