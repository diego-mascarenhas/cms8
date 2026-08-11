<?php

namespace App\Services\Finance;

use App\Models\Category;
use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ServiceCategoryOptionsService
{
    /**
     * @return list<array{id: int, name: string, group: string|null}>
     */
    public function optionsForTeam(int $teamId): array
    {
        return $this->optionsForModules($teamId, ['services']);
    }

    /**
     * Invoice / expense line pickers: Hosting (services) + Desarrollos (projects).
     *
     * @return list<array{id: int, name: string, group: string|null}>
     */
    public function optionsForInvoiceLines(int $teamId): array
    {
        return $this->optionsForModules($teamId, ['services', 'projects']);
    }

    /**
     * @param  list<string>  $moduleKeys
     * @return list<array{id: int, name: string, group: string|null}>
     */
    public function optionsForModules(int $teamId, array $moduleKeys): array
    {
        $moduleIds = Module::query()
            ->whereIn('key', $moduleKeys)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $categoriesQuery = Category::query()
            ->where('status', '>', 0)
            ->where(function ($query) use ($teamId)
            {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            });

        if ($moduleIds !== [])
        {
            $categoriesQuery->whereIn('module_id', $moduleIds);
        } else
        {
            $categoriesQuery->whereNull('module_id');
        }

        $parents = (clone $categoriesQuery)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name']);

        /** @var Collection<int|string, Collection<int, Category>> $childrenByParent */
        $childrenByParent = (clone $categoriesQuery)
            ->whereNotNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->groupBy('parent_id');

        $categoryById = (clone $categoriesQuery)->get(['id', 'name', 'parent_id'])->keyBy('id');
        $parentIds = $parents->pluck('id')->map(fn ($id) => (int) $id)->all();
        $options = [];
        $renderedIds = [];

        foreach ($parents as $parent)
        {
            $children = $childrenByParent->get($parent->id) ?? collect();
            $nested = collect();

            foreach ($children as $child)
            {
                foreach ($childrenByParent->get($child->id) ?? collect() as $grandChild)
                {
                    $nested->push($grandChild);
                }
            }

            if ($children->isEmpty() && $nested->isEmpty())
            {
                $options[] = [
                    'id' => (int) $parent->id,
                    'name' => (string) $parent->name,
                    'group' => null,
                ];
                $renderedIds[(int) $parent->id] = true;

                continue;
            }

            foreach ($children as $child)
            {
                $options[] = [
                    'id' => (int) $child->id,
                    'name' => (string) $child->name,
                    'group' => (string) $parent->name,
                ];
                $renderedIds[(int) $child->id] = true;
            }

            foreach ($nested as $nestedCategory)
            {
                $options[] = [
                    'id' => (int) $nestedCategory->id,
                    'name' => $this->nestedLabel($nestedCategory, $categoryById),
                    'group' => (string) $parent->name,
                ];
                $renderedIds[(int) $nestedCategory->id] = true;
            }
        }

        foreach ($childrenByParent as $parentId => $children)
        {
            if (in_array((int) $parentId, $parentIds, true))
            {
                continue;
            }

            $parentCategory = $categoryById->get((int) $parentId);
            $skipAsNested = $parentCategory
                && $parentCategory->parent_id !== null
                && in_array((int) $parentCategory->parent_id, $parentIds, true);

            if ($skipAsNested)
            {
                continue;
            }

            foreach ($children as $child)
            {
                if (isset($renderedIds[(int) $child->id]))
                {
                    continue;
                }

                $options[] = [
                    'id' => (int) $child->id,
                    'name' => $this->nestedLabel($child, $categoryById),
                    'group' => null,
                ];
            }
        }

        return $options;
    }

    public function belongsToTeamServices(int $teamId, int $categoryId): bool
    {
        return $this->belongsToTeamModules($teamId, $categoryId, ['services']);
    }

    public function belongsToTeamInvoiceLineCategory(int $teamId, int $categoryId): bool
    {
        return $this->belongsToTeamModules($teamId, $categoryId, ['services', 'projects']);
    }

    /**
     * @param  list<string>  $moduleKeys
     */
    public function belongsToTeamModules(int $teamId, int $categoryId, array $moduleKeys): bool
    {
        $moduleIds = Module::query()
            ->whereIn('key', $moduleKeys)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return Category::query()
            ->whereKey($categoryId)
            ->where('status', '>', 0)
            ->where(function (Builder $query) use ($teamId): void
            {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            })
            ->when(
                $moduleIds !== [],
                fn (Builder $query) => $query->whereIn('module_id', $moduleIds),
                fn (Builder $query) => $query->whereNull('module_id'),
            )
            ->exists();
    }

    /**
     * @param  Collection<int|string, Category>  $categoryById
     */
    private function nestedLabel(Category $category, Collection $categoryById): string
    {
        $parts = [(string) $category->name];
        $parentId = $category->parent_id ? (int) $category->parent_id : null;
        $guard = 0;

        while ($parentId && $guard < 5)
        {
            $parent = $categoryById->get($parentId);
            if (! $parent)
            {
                break;
            }

            if ($parent->parent_id === null)
            {
                break;
            }

            array_unshift($parts, (string) $parent->name);
            $parentId = $parent->parent_id ? (int) $parent->parent_id : null;
            $guard++;
        }

        return implode(' › ', $parts);
    }
}
