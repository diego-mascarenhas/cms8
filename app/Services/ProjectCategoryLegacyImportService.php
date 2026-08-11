<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Imports Legacy project categories (categorias_generales where padre = 40)
 * into CMS8 categories for the projects module, resolving by name (not Legacy ID).
 */
class ProjectCategoryLegacyImportService
{
    public const LEGACY_PARENT_ID = 40;

    public const PARENT_CATEGORY_NAME = 'Legacy Project Types';

    /** @var array<string, int> */
    private array $nameToIdCache = [];

    private ?int $projectsModuleId = null;

    private ?int $parentCategoryId = null;

    /**
     * Compare Legacy project categories (padre = 40) with local categories.
     * Prefer preserving Legacy IDs; report conflicts when an ID is already used.
     *
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   summary: array{ok: int, missing: int, id_conflict: int, name_elsewhere: int, total: int},
     *   message: ?string
     * }
     */
    public function analyze(): array
    {
        $summary = [
            'ok' => 0,
            'missing' => 0,
            'id_conflict' => 0,
            'name_elsewhere' => 0,
            'total' => 0,
        ];

        $moduleId = $this->projectsModuleId();
        if ($moduleId === null)
        {
            return [
                'rows' => [],
                'summary' => $summary,
                'message' => 'Projects module not found. Run ModuleSeeder first.',
            ];
        }

        $legacyRows = $this->fetchLegacyProjectCategories();
        $summary['total'] = $legacyRows->count();

        $moduleKeys = Module::query()->pluck('key', 'id')->all();
        $rows = [];

        foreach ($legacyRows as $legacy)
        {
            $legacyId = (int) $legacy->id;
            $legacyName = trim((string) ($legacy->categoria ?? ''));
            $byId = Category::withTrashed()->find($legacyId);
            $byName = $legacyName === ''
                ? null
                : Category::query()
                    ->where('module_id', $moduleId)
                    ->whereNull('team_id')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($legacyName)])
                    ->first();

            $status = 'missing';
            $detail = 'Not present in local categories';
            $localId = null;
            $localModule = null;

            if ($byId)
            {
                $localId = (int) $byId->id;
                $localModule = $moduleKeys[(int) $byId->module_id] ?? ('module#'.$byId->module_id);
                $sameModule = (int) $byId->module_id === $moduleId;
                $sameName = mb_strtolower((string) $byId->name) === mb_strtolower($legacyName);

                if ($sameModule && $sameName && $byId->deleted_at === null)
                {
                    $status = 'ok';
                    $detail = 'Same ID in projects module';
                } elseif ($sameModule && ! $sameName)
                {
                    $status = 'id_conflict';
                    $detail = 'ID exists in projects but name is "'.$byId->name.'"';
                } else
                {
                    $status = 'id_conflict';
                    $detail = 'ID taken by '.$localModule.' ("'.$byId->name.'"'.($byId->deleted_at ? ', soft-deleted' : '').')';
                }
            } elseif ($byName)
            {
                $status = 'name_elsewhere';
                $localId = (int) $byName->id;
                $localModule = 'projects';
                $detail = 'Name exists as projects category id '.$byName->id.' (different from Legacy '.$legacyId.')';
            }

            $summary[$status === 'ok' ? 'ok' : ($status === 'missing' ? 'missing' : ($status === 'name_elsewhere' ? 'name_elsewhere' : 'id_conflict'))]++;

            $rows[] = [
                'legacy_id' => $legacyId,
                'legacy_name' => $legacyName,
                'status' => $status,
                'local_id' => $localId,
                'local_module' => $localModule,
                'detail' => $detail,
                'legacy_orden' => (int) ($legacy->orden ?? 0),
                'legacy_description' => $legacy->descripcion !== null ? (string) $legacy->descripcion : null,
            ];
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
            'message' => null,
        ];
    }

    /**
     * Insert missing Legacy project categories preserving Legacy IDs when free.
     * Skips ID conflicts. Optionally remaps projects.category_id from Legacy.
     *
     * @return array{created: int, updated: int, skipped: int, projects_remapped: int, skipped_rows: list<array<string, mixed>>}
     */
    public function syncPreservingIds(bool $remapProjects = false): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'projects_remapped' => 0,
            'skipped_rows' => [],
        ];

        $moduleId = $this->projectsModuleId();
        if ($moduleId === null)
        {
            throw new \RuntimeException('Projects module not found. Run ModuleSeeder first.');
        }

        $analysis = $this->analyze();
        $parentId = $this->ensureParentCategoryId($moduleId);
        $now = now();

        foreach ($analysis['rows'] as $row)
        {
            if ($row['status'] === 'ok')
            {
                $stats['updated']++;

                continue;
            }

            if ($row['status'] === 'id_conflict')
            {
                $stats['skipped']++;
                $stats['skipped_rows'][] = $row;

                continue;
            }

            if ($row['status'] === 'missing')
            {
                DB::table('categories')->insert([
                    'id' => $row['legacy_id'],
                    'name' => $row['legacy_name'],
                    'module_id' => $moduleId,
                    'team_id' => null,
                    'parent_id' => $parentId,
                    'description' => $row['legacy_description'],
                    'data' => json_encode([
                        'legacy_source' => 'categorias_generales',
                        'legacy_parent_id' => self::LEGACY_PARENT_ID,
                        'legacy_category_id' => $row['legacy_id'],
                    ]),
                    'order' => $row['legacy_orden'] > 0 ? $row['legacy_orden'] : 0,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);
                $stats['created']++;
                $this->nameToIdCache[mb_strtolower($row['legacy_name'])] = $row['legacy_id'];

                continue;
            }

            if ($row['status'] === 'name_elsewhere')
            {
                // Keep existing projects-module row; do not invent a second ID.
                $stats['skipped']++;
                $stats['skipped_rows'][] = $row;
            }
        }

        $this->syncCategoriesAutoIncrement();

        if ($remapProjects)
        {
            $stats['projects_remapped'] = $this->remapProjectCategoryIds();
        }

        return $stats;
    }

    /**
     * Set projects.category_id from Legacy proyectos.id_categoria when that category
     * exists locally in the projects module with the same ID.
     */
    public function remapProjectCategoryIds(?int $projectId = null): int
    {
        $moduleId = $this->projectsModuleId();
        if ($moduleId === null)
        {
            return 0;
        }

        $cmsGroup = (int) env('CMS_GROUP', 502);
        $query = DB::connection('mysql_legacy')
            ->table('proyectos')
            ->where('grupo', $cmsGroup)
            ->where('estado', '>', 0)
            ->whereNotNull('id_categoria')
            ->where('id_categoria', '>', 0)
            ->select('id', 'id_categoria');

        if ($projectId !== null)
        {
            $query->where('id', $projectId);
        }

        $remapped = 0;
        foreach ($query->get() as $legacyProject)
        {
            $legacyCategoryId = (int) $legacyProject->id_categoria;
            $exists = Category::query()
                ->where('id', $legacyCategoryId)
                ->where('module_id', $moduleId)
                ->exists();

            if (! $exists)
            {
                continue;
            }

            $updated = DB::table('projects')
                ->where('id', (int) $legacyProject->id)
                ->where(function ($q) use ($legacyCategoryId)
                {
                    $q->whereNull('category_id')
                        ->orWhere('category_id', '!=', $legacyCategoryId);
                })
                ->update([
                    'category_id' => $legacyCategoryId,
                    'updated_at' => now(),
                ]);

            $remapped += $updated;
        }

        return $remapped;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function fetchLegacyProjectCategories()
    {
        return DB::connection('mysql_legacy')
            ->table('categorias_generales')
            ->where('padre', self::LEGACY_PARENT_ID)
            ->where('estado', '>', 0)
            ->orderBy('orden')
            ->orderBy('categoria')
            ->get(['id', 'categoria', 'descripcion', 'orden', 'estado']);
    }

    private function syncCategoriesAutoIncrement(): void
    {
        \App\Support\DatabaseSequence::sync('categories');
    }

    /**
     * Import all Legacy project categories (padre = 40) into the projects module.
     *
     * @return array{imported: int, updated: int, skipped: int, total_legacy: int, message: ?string}
     */
    public function importAllFromLegacy(): array
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'total_legacy' => 0,
            'message' => null,
        ];

        $moduleId = $this->projectsModuleId();
        if ($moduleId === null)
        {
            $stats['message'] = 'Projects module not found. Run ModuleSeeder first.';

            return $stats;
        }

        $rows = $this->fetchLegacyProjectCategories();

        $stats['total_legacy'] = $rows->count();
        if ($rows->isEmpty())
        {
            $stats['message'] = 'No Legacy project categories found (padre = '.self::LEGACY_PARENT_ID.').';

            return $stats;
        }

        $parentId = $this->ensureParentCategoryId($moduleId);
        $order = 1;

        foreach ($rows as $row)
        {
            $name = trim((string) ($row->categoria ?? ''));
            if ($name === '')
            {
                $stats['skipped']++;

                continue;
            }

            $result = $this->upsertByName(
                $moduleId,
                $parentId,
                $name,
                $row->descripcion !== null ? (string) $row->descripcion : null,
                (int) ($row->orden ?? $order),
                (int) ($row->id ?? 0),
            );

            if ($result === 'imported')
            {
                $stats['imported']++;
            } elseif ($result === 'updated')
            {
                $stats['updated']++;
            } else
            {
                $stats['skipped']++;
            }

            $order++;
        }

        return $stats;
    }

    /**
     * Resolve a Legacy proyectos.id_categoria to a CMS8 projects-module category id.
     * Prefers the same Legacy ID when it already exists under projects.
     */
    public function resolveCategoryIdFromLegacy(?int $legacyCategoryId): ?int
    {
        if ($legacyCategoryId === null || $legacyCategoryId <= 0)
        {
            return null;
        }

        $moduleId = $this->projectsModuleId();
        if ($moduleId === null)
        {
            return null;
        }

        $existingById = Category::query()
            ->whereKey($legacyCategoryId)
            ->where('module_id', $moduleId)
            ->first();
        if ($existingById)
        {
            $this->nameToIdCache[mb_strtolower((string) $existingById->name)] = (int) $existingById->id;

            return (int) $existingById->id;
        }

        $legacyName = DB::connection('mysql_legacy')
            ->table('categorias_generales')
            ->where('id', $legacyCategoryId)
            ->value('categoria');

        if ($legacyName === null || trim((string) $legacyName) === '')
        {
            return null;
        }

        $name = trim((string) $legacyName);
        $cacheKey = mb_strtolower($name);
        if (isset($this->nameToIdCache[$cacheKey]))
        {
            return $this->nameToIdCache[$cacheKey];
        }

        $parentId = $this->ensureParentCategoryId($moduleId);
        $this->upsertByName($moduleId, $parentId, $name, null, 0, $legacyCategoryId);

        return $this->nameToIdCache[$cacheKey] ?? null;
    }

    private function projectsModuleId(): ?int
    {
        if ($this->projectsModuleId !== null)
        {
            return $this->projectsModuleId;
        }

        $id = Module::query()->where('key', 'projects')->value('id');
        $this->projectsModuleId = $id !== null ? (int) $id : null;

        return $this->projectsModuleId;
    }

    private function ensureParentCategoryId(int $moduleId): int
    {
        if ($this->parentCategoryId !== null)
        {
            return $this->parentCategoryId;
        }

        $parent = Category::query()
            ->where('module_id', $moduleId)
            ->whereNull('team_id')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(self::PARENT_CATEGORY_NAME)])
            ->first();

        if (! $parent)
        {
            $parent = Category::query()->create([
                'name' => self::PARENT_CATEGORY_NAME,
                'module_id' => $moduleId,
                'team_id' => null,
                'parent_id' => null,
                'description' => 'Project categories imported from Legacy (categorias_generales padre = '.self::LEGACY_PARENT_ID.')',
                'order' => 0,
                'status' => true,
                'data' => [
                    'legacy_source' => 'categorias_generales',
                    'legacy_parent_id' => self::LEGACY_PARENT_ID,
                ],
            ]);
        }

        $this->parentCategoryId = (int) $parent->id;

        return $this->parentCategoryId;
    }

    /**
     * @return 'imported'|'updated'|'skipped'
     */
    private function upsertByName(
        int $moduleId,
        int $parentId,
        string $name,
        ?string $description,
        int $order,
        int $legacyId,
    ): string {
        $cacheKey = mb_strtolower($name);

        $existing = Category::query()
            ->where('module_id', $moduleId)
            ->whereNull('team_id')
            ->whereRaw('LOWER(name) = ?', [$cacheKey])
            ->first();

        $payload = [
            'name' => $name,
            'module_id' => $moduleId,
            'team_id' => null,
            'parent_id' => $parentId,
            'description' => $description,
            'order' => $order > 0 ? $order : ($existing?->order ?? 0),
            'status' => true,
            'data' => array_filter([
                'legacy_source' => 'categorias_generales',
                'legacy_parent_id' => self::LEGACY_PARENT_ID,
                'legacy_category_id' => $legacyId > 0 ? $legacyId : null,
            ]),
        ];

        if ($existing)
        {
            $existing->fill($payload);
            $existing->save();
            $this->nameToIdCache[$cacheKey] = (int) $existing->id;

            return 'updated';
        }

        $created = Category::query()->create($payload);
        $this->nameToIdCache[$cacheKey] = (int) $created->id;

        return 'imported';
    }
}
