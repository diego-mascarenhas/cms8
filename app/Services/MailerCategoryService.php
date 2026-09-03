<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Module;
use App\Models\Team;
use InvalidArgumentException;

class MailerCategoryService
{
    public const SORT_NAME = 'name';

    public const SORT_MANUAL = 'manual';

    public const SORT_SETTING = 'assistant_category_sort';

    /**
     * @return array{sort: string, categories: list<array{id: int, name: string, color: string|null, contacts_count: int}>}
     */
    public function presentForTeam(Team $team): array
    {
        return [
            'sort' => $this->sortForTeam($team),
            'categories' => $this->listForTeam($team),
        ];
    }

    /**
     * @return list<array{id: int, name: string, color: string|null, contacts_count: int}>
     */
    public function listForTeam(Team $team): array
    {
        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            return [];
        }

        $query = Category::query()
            ->where('module_id', $moduleId)
            ->where('team_id', $team->id)
            ->where('status', '>', 0)
            ->withCount(['contacts as contacts_count' => function ($query)
            {
                $query->withoutGlobalScopes();
            }]);

        $this->applyCategoryOrder($query, $team);

        return $query
            ->get()
            ->map(fn (Category $category): array => $this->presentCategory($category, (int) $category->contacts_count))
            ->values()
            ->all();
    }

    public function sortForTeam(Team $team): string
    {
        $value = (string) $team->getSetting(self::SORT_SETTING, self::SORT_NAME);

        return $value === self::SORT_MANUAL ? self::SORT_MANUAL : self::SORT_NAME;
    }

    /**
     * @return array{sort: string, categories: list<array{id: int, name: string, color: string|null, contacts_count: int}>}
     */
    public function setSortForTeam(Team $team, string $sort): array
    {
        $next = $sort === self::SORT_MANUAL ? self::SORT_MANUAL : self::SORT_NAME;
        $current = $this->sortForTeam($team);

        if ($next === self::SORT_MANUAL && $current !== self::SORT_MANUAL)
        {
            $this->seedManualOrderFromName($team);
        }

        $team->setSetting(self::SORT_SETTING, $next, [
            'type' => 'string',
            'group' => 'assistant',
        ]);

        return $this->presentForTeam($team);
    }

    /**
     * @param  list<int|string>  $ids
     * @return array{sort: string, categories: list<array{id: int, name: string, color: string|null, contacts_count: int}>}
     */
    public function reorderForTeam(Team $team, array $ids): array
    {
        $ownedIds = array_column($this->listForTeam($team), 'id');
        $requested = $this->normalizedIds($ids);
        $sortedOwned = $ownedIds;
        $sortedRequested = $requested;
        sort($sortedOwned);
        sort($sortedRequested);

        if ($ownedIds === [] || $sortedOwned !== $sortedRequested)
        {
            throw new InvalidArgumentException('The selected category is invalid.');
        }

        if ($this->sortForTeam($team) !== self::SORT_MANUAL)
        {
            $team->setSetting(self::SORT_SETTING, self::SORT_MANUAL, [
                'type' => 'string',
                'group' => 'assistant',
            ]);
        }

        $moduleId = $this->contactsModuleId();
        foreach ($requested as $index => $id)
        {
            Category::query()
                ->where('module_id', $moduleId)
                ->where('team_id', $team->id)
                ->whereKey($id)
                ->update(['order' => $index + 1]);
        }

        return $this->presentForTeam($team);
    }

    /**
     * @return array{id: int, name: string, color: string|null, contacts_count: int}
     */
    public function createForTeam(Team $team, string $name, ?string $color = null): array
    {
        $name = trim($name);
        if ($name === '')
        {
            throw new InvalidArgumentException('Enter a category name.');
        }

        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            throw new InvalidArgumentException('The contacts module is not available.');
        }

        $normalized = mb_strtolower($name);
        foreach ($this->listForTeam($team) as $row)
        {
            if (mb_strtolower($row['name']) === $normalized)
            {
                throw new InvalidArgumentException('Ya existe una categoría con ese nombre.');
            }
        }

        $category = Category::query()->create([
            'name' => $name,
            'module_id' => $moduleId,
            'team_id' => $team->id,
            'parent_id' => null,
            'order' => $this->nextManualOrder($team, $moduleId),
            'status' => 1,
            'color' => self::normalizeColor($color),
        ]);

        return $this->presentCategory($category, 0);
    }

    /**
     * @param  array{name?: string, color?: string|null}  $attributes
     * @return array{id: int, name: string, color: string|null, contacts_count: int}
     */
    public function updateForTeam(Team $team, Category $category, array $attributes): array
    {
        $owned = $this->teamOwnedCategory($team, $category);
        if ($owned === null)
        {
            throw new InvalidArgumentException('The selected category is invalid.');
        }

        if (array_key_exists('name', $attributes))
        {
            $name = trim((string) $attributes['name']);
            if ($name === '')
            {
                throw new InvalidArgumentException('Enter a category name.');
            }

            $normalized = mb_strtolower($name);
            foreach ($this->listForTeam($team) as $row)
            {
                if ($row['id'] !== (int) $owned->id && mb_strtolower($row['name']) === $normalized)
                {
                    throw new InvalidArgumentException('Ya existe una categoría con ese nombre.');
                }
            }

            $owned->name = $name;
        }

        if (array_key_exists('color', $attributes))
        {
            $owned->color = self::normalizeColor($attributes['color'] ?? null);
        }

        $owned->save();

        foreach ($this->listForTeam($team) as $row)
        {
            if ($row['id'] === (int) $owned->id)
            {
                return $row;
            }
        }

        return $this->presentCategory($owned->fresh(), 0);
    }

    public function deleteForTeam(Team $team, Category $category): void
    {
        $owned = $this->teamOwnedCategory($team, $category);
        if ($owned === null)
        {
            throw new InvalidArgumentException('The selected category is invalid.');
        }

        $owned->delete();
    }

    public static function normalizeColor(mixed $color): ?string
    {
        if (! is_string($color))
        {
            return null;
        }

        $value = strtolower(trim($color));
        if (preg_match('/^#([0-9a-f]{6})$/', $value) !== 1)
        {
            return null;
        }

        return $value;
    }

    private function applyCategoryOrder(mixed $query, Team $team): void
    {
        if ($this->sortForTeam($team) === self::SORT_MANUAL)
        {
            $query->orderBy('order')->orderBy('name');

            return;
        }

        $query->orderBy('name');
    }

    private function seedManualOrderFromName(Team $team): void
    {
        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            return;
        }

        $categories = Category::query()
            ->where('module_id', $moduleId)
            ->where('team_id', $team->id)
            ->where('status', '>', 0)
            ->orderBy('name')
            ->get();

        foreach ($categories->values() as $index => $category)
        {
            $category->forceFill(['order' => $index + 1])->save();
        }
    }

    private function nextManualOrder(Team $team, int $moduleId): int
    {
        if ($this->sortForTeam($team) !== self::SORT_MANUAL)
        {
            return 0;
        }

        return ((int) Category::query()
            ->where('module_id', $moduleId)
            ->where('team_id', $team->id)
            ->max('order')) + 1;
    }

    /**
     * @return array{id: int, name: string, color: string|null, contacts_count: int}
     */
    private function presentCategory(Category $category, int $contactsCount): array
    {
        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'color' => self::normalizeColor($category->color),
            'contacts_count' => $contactsCount,
        ];
    }

    private function teamOwnedCategory(Team $team, Category $category): ?Category
    {
        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            return null;
        }

        if ((int) $category->module_id !== $moduleId || (int) $category->team_id !== (int) $team->id)
        {
            return null;
        }

        return $category;
    }

    /**
     * @param  list<int|string>  $categoryIds
     * @return list<int>
     */
    private function normalizedIds(array $categoryIds): array
    {
        $normalized = [];
        foreach ($categoryIds as $id)
        {
            $int = (int) $id;
            if ($int > 0)
            {
                $normalized[$int] = $int;
            }
        }

        return array_values($normalized);
    }

    private function contactsModuleId(): ?int
    {
        $moduleId = Module::query()->where('key', 'contacts')->value('id');

        return $moduleId !== null ? (int) $moduleId : null;
    }
}
