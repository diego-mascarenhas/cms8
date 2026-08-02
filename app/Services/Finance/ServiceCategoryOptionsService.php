<?php

namespace App\Services\Finance;

use App\Models\Category;
use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;

class ServiceCategoryOptionsService
{
    /**
     * @return list<array{id: int, name: string, group: string|null}>
     */
    public function optionsForTeam(int $teamId): array
    {
        $moduleId = Module::query()->where('key', 'services')->value('id');

        $categoriesQuery = Category::query()
            ->where('status', '>', 0)
            ->where(function ($query) use ($teamId)
            {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            });

        if ($moduleId)
        {
            $categoriesQuery->where('module_id', $moduleId);
        } else
        {
            $categoriesQuery->whereNull('module_id');
        }

        $parents = (clone $categoriesQuery)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $childrenByParent = (clone $categoriesQuery)
            ->whereNotNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->groupBy('parent_id');

        $options = [];
        $parentIds = $parents->pluck('id')->all();

        foreach ($parents as $parent)
        {
            $children = $childrenByParent->get($parent->id);

            if ($children === null || $children->isEmpty())
            {
                $options[] = [
                    'id' => (int) $parent->id,
                    'name' => (string) $parent->name,
                    'group' => null,
                ];

                continue;
            }

            foreach ($children as $child)
            {
                $options[] = [
                    'id' => (int) $child->id,
                    'name' => (string) $child->name,
                    'group' => (string) $parent->name,
                ];
            }
        }

        foreach ($childrenByParent as $parentId => $children)
        {
            if (in_array((int) $parentId, $parentIds, true))
            {
                continue;
            }

            foreach ($children as $child)
            {
                $options[] = [
                    'id' => (int) $child->id,
                    'name' => (string) $child->name,
                    'group' => null,
                ];
            }
        }

        return $options;
    }

    public function belongsToTeamServices(int $teamId, int $categoryId): bool
    {
        $moduleId = Module::query()->where('key', 'services')->value('id');

        return Category::query()
            ->whereKey($categoryId)
            ->where('status', '>', 0)
            ->where(function (Builder $query) use ($teamId): void
            {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            })
            ->when(
                $moduleId,
                fn (Builder $query) => $query->where('module_id', $moduleId),
                fn (Builder $query) => $query->whereNull('module_id'),
            )
            ->exists();
    }
}
