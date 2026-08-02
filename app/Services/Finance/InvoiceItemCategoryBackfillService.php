<?php

namespace App\Services\Finance;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Service;
use App\Services\Billing\ServiceSyncImporter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceItemCategoryBackfillService
{
    public function __construct(
        private readonly ServiceSyncImporter $serviceSyncImporter,
    ) {}

    /**
     * @return array{
     *     processed: int,
     *     updated: int,
     *     skipped: int,
     *     from_service: int,
     *     from_prior: int,
     *     services_updated: int,
     * }
     */
    public function backfill(
        ?int $teamId,
        int $limit,
        bool $dryRun,
        bool $fromPriorInvoices,
        bool $replaceGenericParents = false,
    ): array {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'from_service' => 0,
            'from_prior' => 0,
            'services_updated' => 0,
        ];

        $genericParentIds = $this->genericParentCategoryIds($teamId);

        $query = InvoiceItem::query()
            ->select('invoice_items.*')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.operation', 'sell')
            ->where('invoices.source_provider', 'stripe')
            ->whereNotNull('invoices.source_reference_id')
            ->orderBy('invoice_items.id');

        if ($replaceGenericParents && $genericParentIds !== [])
        {
            $query->where(function ($builder) use ($genericParentIds): void
            {
                $builder->whereNull('invoice_items.category_id')
                    ->orWhereIn('invoice_items.category_id', $genericParentIds);
            });
        } else
        {
            $query->whereNull('invoice_items.category_id');
        }

        if ($teamId)
        {
            $query->where('invoices.team_id', $teamId);
        }

        $items = $query->limit($limit)->get();

        foreach ($items as $item)
        {
            if (! $item instanceof InvoiceItem)
            {
                continue;
            }

            $stats['processed']++;

            $invoice = $item->invoice()->withoutGlobalScopes()->first();
            if (! $invoice instanceof Invoice)
            {
                $stats['skipped']++;

                continue;
            }

            $resolved = $this->resolveCategoryForItem($item, $invoice, $fromPriorInvoices, $genericParentIds);

            if ($resolved === null)
            {
                $stats['skipped']++;

                continue;
            }

            [$categoryId, $source, $serviceToUpdate] = $resolved;

            if (! $dryRun)
            {
                $item->forceFill(['category_id' => $categoryId])->save();

                if ($serviceToUpdate instanceof Service && blank($serviceToUpdate->category_id))
                {
                    $serviceToUpdate->forceFill(['category_id' => $categoryId])->save();
                    $stats['services_updated']++;
                }
            } elseif ($serviceToUpdate instanceof Service && blank($serviceToUpdate->category_id))
            {
                $stats['services_updated']++;
            }

            $stats['updated']++;
            if ($source === 'service')
            {
                $stats['from_service']++;
            } else
            {
                $stats['from_prior']++;
            }
        }

        return $stats;
    }

    /**
     * @param  list<int>  $genericParentIds
     * @return array{0: int, 1: string, 2: Service|null}|null
     */
    public function resolveCategoryForItem(
        InvoiceItem $item,
        Invoice $invoice,
        bool $fromPriorInvoices,
        array $genericParentIds = [],
    ): ?array {
        $linkedService = $this->findLinkedServiceForInvoice($invoice);

        $fromService = $this->resolveFromLinkedService($invoice, $linkedService);
        if ($fromService !== null)
        {
            return [$fromService, 'service', null];
        }

        if (! $fromPriorInvoices)
        {
            return null;
        }

        return $this->resolveFromPriorInvoices($item, $invoice, $linkedService, $genericParentIds);
    }

    private function resolveFromLinkedService(Invoice $invoice, ?Service $linkedService): ?int
    {
        if ($linkedService?->category_id)
        {
            return (int) $linkedService->category_id;
        }

        $sync = $this->findInvoiceSync($invoice);
        if (! $sync || ! filled($sync->stripe_subscription_id))
        {
            return null;
        }

        return $this->serviceSyncImporter->resolveCategoryIdForInvoiceItem(
            (int) $invoice->team_id,
            (string) $sync->stripe_subscription_id,
        );
    }

    /**
     * @param  list<int>  $genericParentIds
     * @return array{0: int, 1: string, 2: Service|null}|null
     */
    private function resolveFromPriorInvoices(
        InvoiceItem $item,
        Invoice $invoice,
        ?Service $linkedService,
        array $genericParentIds,
    ): ?array {
        if (! $invoice->enterprise_id)
        {
            return null;
        }

        $description = $this->normalizeDescription((string) $item->description);
        if ($description === '')
        {
            return null;
        }

        $amount = $this->lineNetAmount($item);

        $priorMatches = $this->priorMatchesWithNormalizedDescription($item, $invoice, $amount, $genericParentIds, $description);

        if ($priorMatches->isEmpty())
        {
            return null;
        }

        $distinctCategoryIds = $priorMatches->pluck('category_id')->map(fn ($id) => (int) $id)->unique()->values();
        if ($distinctCategoryIds->count() !== 1)
        {
            return null;
        }

        $categoryId = (int) $distinctCategoryIds->first();

        if ($linkedService?->category_id)
        {
            if ((int) $linkedService->category_id !== $categoryId)
            {
                return null;
            }

            return [$categoryId, 'prior', null];
        }

        // Linked service exists but has no category: fill both when prior is unambiguous.
        if ($linkedService && blank($linkedService->category_id))
        {
            return [$categoryId, 'prior', $linkedService];
        }

        // No linked service: still safe when description + amount uniquely map to one category.
        return [$categoryId, 'prior', null];
    }

    /**
     * @param  list<int>  $genericParentIds
     * @return Collection<int, object{category_id: int, match_count: int}>
     */
    private function priorMatchesWithNormalizedDescription(
        InvoiceItem $item,
        Invoice $invoice,
        float $amount,
        array $genericParentIds,
        ?string $targetNormalized = null,
    ): Collection {
        $targetNormalized ??= $this->normalizeDescription((string) $item->description);
        $amountCents = (int) round($amount * 100);

        $candidates = InvoiceItem::query()
            ->select([
                'invoice_items.id',
                'invoice_items.category_id',
                'invoice_items.description',
                'invoice_items.quantity',
                'invoice_items.unit_price',
                'invoice_items.discount',
            ])
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.team_id', $invoice->team_id)
            ->where('invoices.enterprise_id', $invoice->enterprise_id)
            ->where('invoices.operation', 'sell')
            ->whereNotIn('invoices.status', InvoiceAnalyticsService::EXCLUDED_INVOICE_STATUSES)
            ->whereNotNull('invoice_items.category_id')
            ->when($genericParentIds !== [], fn ($q) => $q->whereNotIn('invoice_items.category_id', $genericParentIds))
            ->where('invoice_items.id', '!=', $item->id)
            ->orderByDesc('invoice_items.id')
            ->limit(500)
            ->get()
            ->filter(function (InvoiceItem $prior) use ($targetNormalized, $amountCents): bool
            {
                if ($this->normalizeDescription((string) $prior->description) !== $targetNormalized)
                {
                    return false;
                }

                return (int) round($this->lineNetAmount($prior) * 100) === $amountCents;
            });

        if ($candidates->isEmpty())
        {
            return collect();
        }

        return $candidates
            ->groupBy(fn (InvoiceItem $prior) => (int) $prior->category_id)
            ->map(fn (Collection $group, $categoryId) => (object) [
                'category_id' => (int) $categoryId,
                'match_count' => $group->count(),
            ])
            ->values();
    }

    private function findLinkedServiceForInvoice(Invoice $invoice): ?Service
    {
        $sync = $this->findInvoiceSync($invoice);
        if (! $sync || ! filled($sync->stripe_subscription_id))
        {
            return null;
        }

        $serviceSync = \App\Models\ServiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('stripe_id', $sync->stripe_subscription_id)
            ->first();

        if (! $serviceSync)
        {
            return null;
        }

        return $this->serviceSyncImporter->findLinkedService($serviceSync);
    }

    private function findInvoiceSync(Invoice $invoice): ?InvoiceSync
    {
        if (! filled($invoice->source_reference_id))
        {
            return null;
        }

        return InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $invoice->source_reference_id)
            ->first();
    }

    /**
     * Root categories that have children — too coarse for prior matching.
     *
     * @return list<int>
     */
    public function genericParentCategoryIds(?int $teamId): array
    {
        $query = Category::query()
            ->whereNull('parent_id')
            ->where('status', '>', 0)
            ->whereExists(function ($builder): void
            {
                $builder->select(DB::raw('1'))
                    ->from('categories as children')
                    ->whereColumn('children.parent_id', 'categories.id')
                    ->whereNull('children.deleted_at')
                    ->where('children.status', '>', 0);
            });

        if ($teamId)
        {
            $query->where(function ($builder) use ($teamId): void
            {
                $builder->whereNull('team_id')->orWhere('team_id', $teamId);
            });
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function normalizeDescription(string $description): string
    {
        $normalized = mb_strtolower(trim($description));
        $normalized = preg_replace('/^\d+\s*[×x]\s*/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public function lineNetAmount(InvoiceItem $item): float
    {
        return round(
            ((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount ?? 0),
            2,
        );
    }
}
