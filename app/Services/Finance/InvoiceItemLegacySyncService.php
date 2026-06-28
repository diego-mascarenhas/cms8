<?php

namespace App\Services\Finance;

use App\Models\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceItemLegacySyncService
{
    public function __construct(
        private readonly InvoiceCurrencyService $invoiceCurrencyService,
    ) {}

    public function cmsGroup(): int
    {
        return (int) env('CMS_GROUP', 502);
    }

    public function legacyConnectionAvailable(): bool
    {
        try
        {
            DB::connection('mysql_legacy')->getPdo();
        } catch (\Throwable)
        {
            return false;
        }

        return Schema::connection('mysql_legacy')->hasTable('facturas_items');
    }

    /**
     * Legacy facturas_items.id_categoria maps to categories.id (parent or child with parent_id).
     */
    public function resolveCategoryId(?int $legacyCategoryId, ?int $teamId = null): ?int
    {
        if ($legacyCategoryId === null || $legacyCategoryId <= 0)
        {
            return null;
        }

        $query = DB::table('categories')->where('id', $legacyCategoryId);

        if ($teamId !== null)
        {
            $query->where(function ($builder) use ($teamId): void
            {
                $builder->where('team_id', $teamId)->orWhereNull('team_id');
            });
        }

        if (! $query->exists())
        {
            return null;
        }

        return $legacyCategoryId;
    }

    /**
     * Import every active legacy category into categories (parents and children via parent_id).
     *
     * @return array{
     *     total_legacy: int,
     *     imported_parents: int,
     *     updated_parents: int,
     *     imported_children: int,
     *     updated_children: int,
     *     skipped_children_missing_parent: int,
     *     skipped_team_conflict: int,
     * }
     */
    public function importAllCategoriesFromLegacy(int $teamId, bool $dryRun = false): array
    {
        $stats = [
            'total_legacy' => 0,
            'imported_parents' => 0,
            'updated_parents' => 0,
            'imported_children' => 0,
            'updated_children' => 0,
            'skipped_children_missing_parent' => 0,
            'skipped_team_conflict' => 0,
        ];

        if (! Schema::connection('mysql_legacy')->hasTable('categorias_generales'))
        {
            return $stats;
        }

        $serviceModuleId = Module::query()->where('key', 'services')->value('id');

        $allCategories = DB::connection('mysql_legacy')
            ->table('categorias_generales')
            ->where('grupo', $this->cmsGroup())
            ->where('estado', '>', 0)
            ->get();

        $stats['total_legacy'] = $allCategories->count();

        if ($allCategories->isEmpty())
        {
            return $stats;
        }

        $parentCategories = $allCategories->filter(
            fn (object $row): bool => $row->padre === null || (int) $row->padre === 0,
        );

        $childCategories = $allCategories->filter(
            fn (object $row): bool => $row->padre !== null && (int) $row->padre > 0,
        );

        foreach ($parentCategories as $row)
        {
            if ($dryRun)
            {
                $this->incrementCategoryUpsertStat(
                    $stats,
                    $this->previewCategoryUpsertResult((int) $row->id, $teamId),
                    isParent: true,
                );

                continue;
            }

            $result = $this->upsertCategoryFromLegacyRow($row, $teamId, $serviceModuleId, null);
            $this->incrementCategoryUpsertStat($stats, $result, isParent: true);
        }

        foreach ($childCategories as $row)
        {
            if (! $this->legacyParentCategoryExists((int) $row->padre, $teamId))
            {
                $stats['skipped_children_missing_parent']++;

                continue;
            }

            if ($dryRun)
            {
                $this->incrementCategoryUpsertStat(
                    $stats,
                    $this->previewCategoryUpsertResult((int) $row->id, $teamId),
                    isParent: false,
                );

                continue;
            }

            $result = $this->upsertCategoryFromLegacyRow($row, $teamId, $serviceModuleId, (int) $row->padre);
            $this->incrementCategoryUpsertStat($stats, $result, isParent: false);
        }

        if (! $dryRun && $stats['total_legacy'] > 0)
        {
            $this->syncTableAutoIncrement('categories');
        }

        return $stats;
    }

    /**
     * @return array{
     *     imported_parents: int,
     *     imported_children: int,
     *     updated_parents: int,
     *     updated_children: int,
     *     missing_in_legacy: int,
     *     skipped_team_conflict: int,
     * }
     */
    public function importMissingCategoriesFromLegacy(Collection $legacyCategoryIds, int $teamId): array
    {
        $stats = [
            'imported_parents' => 0,
            'imported_children' => 0,
            'updated_parents' => 0,
            'updated_children' => 0,
            'missing_in_legacy' => 0,
            'skipped_team_conflict' => 0,
        ];

        if ($legacyCategoryIds->isEmpty() || ! $this->legacyConnectionAvailable())
        {
            return $stats;
        }

        if (! Schema::connection('mysql_legacy')->hasTable('categorias_generales'))
        {
            return $stats;
        }

        $serviceModuleId = Module::query()->where('key', 'services')->value('id');

        $legacyRows = DB::connection('mysql_legacy')
            ->table('categorias_generales')
            ->where('grupo', $this->cmsGroup())
            ->whereIn('id', $legacyCategoryIds->unique()->values()->all())
            ->get()
            ->keyBy('id');

        foreach ($legacyCategoryIds->unique() as $legacyCategoryId)
        {
            $legacyCategoryId = (int) $legacyCategoryId;
            if ($this->resolveCategoryId($legacyCategoryId, $teamId) !== null)
            {
                continue;
            }

            $row = $legacyRows->get($legacyCategoryId);
            if ($row === null)
            {
                $stats['missing_in_legacy']++;

                continue;
            }

            $isParent = $row->padre === null || (int) $row->padre === 0;

            if ($isParent)
            {
                $result = $this->upsertCategoryFromLegacyRow($row, $teamId, $serviceModuleId, null);
                $this->incrementCategoryUpsertStat($stats, $result, isParent: true);
            } else
            {
                $parentRow = $legacyRows->get((int) $row->padre)
                    ?? DB::connection('mysql_legacy')
                        ->table('categorias_generales')
                        ->where('grupo', $this->cmsGroup())
                        ->where('id', (int) $row->padre)
                        ->first();

                if ($parentRow !== null && ! $this->legacyParentCategoryExists((int) $parentRow->id, $teamId))
                {
                    $parentResult = $this->upsertCategoryFromLegacyRow($parentRow, $teamId, $serviceModuleId, null);
                    $this->incrementCategoryUpsertStat($stats, $parentResult, isParent: true);
                }

                if (! $this->legacyParentCategoryExists((int) $row->padre, $teamId))
                {
                    continue;
                }

                $childResult = $this->upsertCategoryFromLegacyRow($row, $teamId, $serviceModuleId, (int) $row->padre);
                $this->incrementCategoryUpsertStat($stats, $childResult, isParent: false);
            }
        }

        return $stats;
    }

    /**
     * @return array{
     *     processed: int,
     *     created: int,
     *     updated: int,
     *     skipped_no_invoice: int,
     *     category_assigned: int,
     *     still_uncategorized: int,
     *     categories_imported: array<string, int>,
     * }
     */
    public function resyncItems(
        ?int $teamId = null,
        ?int $invoiceId = null,
        ?int $itemId = null,
        bool $importCategories = false,
        bool $onlyMissingCategories = true,
        bool $dryRun = false,
        ?int $limit = null,
        int $chunkSize = 500,
        int $categoryTeamId = 2,
    ): array {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped_no_invoice' => 0,
            'category_assigned' => 0,
            'still_uncategorized' => 0,
            'categories_imported' => [
                'imported_parents' => 0,
                'imported_children' => 0,
                'updated_parents' => 0,
                'updated_children' => 0,
                'missing_in_legacy' => 0,
                'skipped_team_conflict' => 0,
            ],
        ];

        if (! $this->legacyConnectionAvailable())
        {
            return $stats;
        }

        $query = DB::connection('mysql_legacy')
            ->table('facturas_items')
            ->where('grupo', $this->cmsGroup())
            ->select('id', 'id_factura', 'id_categoria', 'descripcion', 'valor', 'descuento', 'fecha_alta', 'fecha_modificacion')
            ->orderBy('id');

        if ($itemId !== null)
        {
            $query->where('id', $itemId);
        }

        if ($invoiceId !== null)
        {
            $query->where('id_factura', $invoiceId);
        }

        if ($teamId !== null)
        {
            $invoiceIds = DB::table('invoices')->where('team_id', $teamId)->pluck('id');
            if ($invoiceIds->isEmpty())
            {
                return $stats;
            }
            $query->whereIn('id_factura', $invoiceIds);
        }

        if ($limit !== null)
        {
            $query->limit($limit);
        }

        $pendingCategoryIds = collect();

        $query->chunk($chunkSize, function (Collection $legacyItems) use (
            &$stats,
            &$pendingCategoryIds,
            $importCategories,
            $onlyMissingCategories,
            $dryRun,
            $categoryTeamId,
        ): void {
            if ($importCategories)
            {
                $legacyItems->pluck('id_categoria')
                    ->filter(fn ($id) => $id !== null && (int) $id > 0)
                    ->each(fn ($id) => $pendingCategoryIds->push((int) $id));

                $categoryStats = $this->importMissingCategoriesFromLegacy($pendingCategoryIds->unique()->values(), $categoryTeamId);
                foreach ($categoryStats as $key => $value)
                {
                    $stats['categories_imported'][$key] += $value;
                }
                $pendingCategoryIds = collect();
            }

            foreach ($legacyItems as $legacyItem)
            {
                $stats['processed']++;

                $invoice = DB::table('invoices')->where('id', $legacyItem->id_factura)->first(['team_id']);
                if ($invoice === null)
                {
                    $stats['skipped_no_invoice']++;

                    continue;
                }

                $invoiceTeamId = (int) ($invoice->team_id ?? $categoryTeamId);

                $categoryId = $this->resolveCategoryId(
                    $legacyItem->id_categoria !== null ? (int) $legacyItem->id_categoria : null,
                    $invoiceTeamId,
                );

                $payload = [
                    'invoice_id' => (int) $legacyItem->id_factura,
                    'category_id' => $categoryId,
                    'description' => $legacyItem->descripcion,
                    'quantity' => 1.0,
                    'unit_price' => (float) ($legacyItem->valor ?? 0),
                    'discount' => (float) ($legacyItem->descuento ?? 0),
                    'tax_percentage' => 0.0,
                    'created_at' => $legacyItem->fecha_alta ?? now(),
                    'updated_at' => $legacyItem->fecha_modificacion ?? now(),
                ];

                if ($categoryId !== null)
                {
                    $stats['category_assigned']++;
                } else
                {
                    $stats['still_uncategorized']++;
                }

                $existing = DB::table('invoice_items')->where('id', $legacyItem->id)->first();

                if ($existing === null)
                {
                    if (! $dryRun)
                    {
                        DB::table('invoice_items')->insert(array_merge(['id' => (int) $legacyItem->id], $payload));
                    }
                    $stats['created']++;

                    continue;
                }

                if ($onlyMissingCategories && $existing->category_id !== null)
                {
                    continue;
                }

                $updatePayload = $payload;
                unset($updatePayload['created_at']);

                $hasChanges = $this->itemNeedsUpdate($existing, $updatePayload);
                if (! $hasChanges)
                {
                    continue;
                }

                if (! $dryRun)
                {
                    DB::table('invoice_items')
                        ->where('id', (int) $legacyItem->id)
                        ->update($updatePayload);
                }

                $stats['updated']++;
            }
        });

        return $stats;
    }

    /**
     * @return array{updated: int, legacy: int, stripe: int, manual_default: int}
     */
    public function resyncInvoiceCurrencies(
        ?int $teamId = null,
        bool $onlyNull = false,
        bool $dryRun = false,
    ): array {
        return $this->invoiceCurrencyService->resync(
            teamId: $teamId,
            fromLegacy: true,
            onlyNull: $onlyNull,
            dryRun: $dryRun,
            manualDefault: true,
        );
    }

    private function itemNeedsUpdate(object $existing, array $payload): bool
    {
        foreach ($payload as $key => $value)
        {
            if (! property_exists($existing, $key))
            {
                continue;
            }

            $current = $existing->{$key};

            if (in_array($key, ['quantity', 'unit_price', 'discount', 'tax_percentage'], true))
            {
                if (round((float) $current, 2) !== round((float) $value, 2))
                {
                    return true;
                }

                continue;
            }

            if ((string) $current !== (string) $value)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Create or update a category using categorias_generales.id as categories.id (unchanged).
     */
    public function upsertCategoryFromLegacyRow(object $legacyRow, int $teamId, ?int $serviceModuleId, ?int $parentId): string
    {
        $categoryData = $this->legacyCategoryPayload($legacyRow, $teamId, $serviceModuleId, $parentId);

        return $this->upsertCategoryWithLegacyId($categoryData);
    }

    private function legacyParentCategoryExists(int $legacyParentId, int $teamId): bool
    {
        return $this->resolveCategoryId($legacyParentId, $teamId) !== null;
    }

    private function previewCategoryUpsertResult(int $legacyCategoryId, int $teamId): string
    {
        $existing = DB::table('categories')->where('id', $legacyCategoryId)->first();

        if ($existing === null)
        {
            return 'imported';
        }

        if ($this->hasTeamConflict($existing->team_id, $teamId))
        {
            return 'skipped_team_conflict';
        }

        return 'updated';
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function incrementCategoryUpsertStat(array &$stats, string $result, bool $isParent): void
    {
        if ($result === 'skipped_team_conflict')
        {
            $stats['skipped_team_conflict']++;

            return;
        }

        $group = $isParent ? 'parents' : 'children';

        if ($result === 'imported')
        {
            $stats["imported_{$group}"]++;

            return;
        }

        $stats["updated_{$group}"]++;
    }

    private function hasTeamConflict(mixed $existingTeamId, int $targetTeamId): bool
    {
        if ($existingTeamId === null)
        {
            return false;
        }

        return (int) $existingTeamId !== $targetTeamId;
    }

    private function upsertCategoryWithLegacyId(array $categoryData): string
    {
        $legacyId = (int) $categoryData['id'];
        $targetTeamId = (int) $categoryData['team_id'];
        $existing = DB::table('categories')->where('id', $legacyId)->first();

        if ($existing === null)
        {
            DB::table('categories')->insert($categoryData);

            return 'imported';
        }

        if ($this->hasTeamConflict($existing->team_id, $targetTeamId))
        {
            return 'skipped_team_conflict';
        }

        DB::table('categories')->where('id', $legacyId)->update([
            'name' => $categoryData['name'],
            'module_id' => $categoryData['module_id'],
            'team_id' => $categoryData['team_id'],
            'parent_id' => $categoryData['parent_id'],
            'description' => $categoryData['description'],
            'data' => $categoryData['data'],
            'order' => $categoryData['order'],
            'status' => $categoryData['status'],
            'updated_at' => $categoryData['updated_at'],
            'deleted_at' => null,
        ]);

        return 'updated';
    }

    private function syncTableAutoIncrement(string $table, string $column = 'id'): void
    {
        if (DB::getDriverName() !== 'mysql')
        {
            return;
        }

        $maxValue = (int) DB::table($table)->max($column);
        $next = max($maxValue + 1, 1);
        $escapedTable = str_replace('`', '``', $table);

        DB::statement("ALTER TABLE `{$escapedTable}` AUTO_INCREMENT = {$next}");
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyCategoryPayload(object $data, int $teamId, ?int $serviceModuleId, ?int $parentId): array
    {
        return [
            'id' => (int) $data->id,
            'name' => (string) $data->categoria,
            'module_id' => $serviceModuleId,
            'team_id' => $teamId,
            'parent_id' => $parentId,
            'description' => strip_tags((string) ($data->descripcion ?? '')),
            'data' => json_encode([
                'currency_id' => $data->id_moneda ?? null,
                'price' => $data->valor ?? null,
                'discount' => $data->descuento ?? null,
                'frequency' => $data->frecuencia ?? null,
                'type_id' => $data->id_tipo ?? null,
                'characteristics' => $data->caracteristicas ?? null,
                'legacy_source' => 'categorias_generales',
            ]),
            'order' => (int) ($data->orden ?? 0),
            'status' => (int) ($data->estado ?? 1),
            'created_at' => $data->fecha_alta ?? now(),
            'updated_at' => $data->fecha_modificacion ?? now(),
        ];
    }
}
