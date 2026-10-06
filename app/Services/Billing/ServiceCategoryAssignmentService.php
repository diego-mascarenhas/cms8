<?php

namespace App\Services\Billing;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Service;
use App\Models\ServiceSync;

class ServiceCategoryAssignmentService
{
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

        return $service?->category_id ? (int) $service->category_id : null;
    }

    public function pushInvoiceCategory(Invoice $invoice, ?int $categoryId): void
    {
        $sync = $this->subscriptionSyncForInvoice($invoice);
        if (! $sync)
        {
            return;
        }

        $name = null;
        if ($categoryId)
        {
            $name = Category::query()->whereKey($categoryId)->value('name');
            $name = filled($name) ? (string) $name : null;
        }

        $this->stripeCategoryMetadataWriter->write($sync, $name);
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
