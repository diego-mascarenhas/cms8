<?php

namespace App\Services\WhatsApp;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\Team;
use App\Services\InboxContactAccessService;
use App\Support\DatabaseSequence;
use InvalidArgumentException;

/**
 * Contact categories as the inbox thread header shows them: only the ones the contacts module
 * offers, so a tag left by another module's import never surfaces next to the contact name.
 */
class WhatsAppThreadCategoryService
{
    public const SORT_NAME = 'name';

    public const SORT_MANUAL = 'manual';

    public const SORT_SETTING = 'assistant_category_sort';

    /**
     * @return array{contact_id: int|null, selected: list<array{id: int, name: string, color: string|null}>, available: list<array{id: int, name: string, color: string|null}>}
     */
    public function present(?Team $team, ?Contact $contact): array
    {
        return [
            'contact_id' => $contact !== null ? (int) $contact->id : null,
            'selected' => $team !== null && $contact !== null ? $this->selectedFor($team, $contact) : [],
            'available' => $team !== null ? $this->availableFor($team) : [],
        ];
    }

    /**
     * @return array{contact_id: int|null, name: string, phone: string, email: string|null, status_id: int|null, statuses: list<array{id: int, name: string, color: string|null}>, user: array{id: int, name: string, email: string, staff: bool}|null}
     */
    public function contactMeta(?Team $team, ?Contact $contact, string $digits = ''): array
    {
        $phone = preg_replace('/[^0-9]/', '', $digits) ?? '';
        if ($phone === '' && $contact?->phone)
        {
            $phone = preg_replace('/[^0-9]/', '', (string) $contact->phone) ?? '';
        }

        $name = '';
        if ($contact !== null)
        {
            $name = trim($contact->name.' '.(string) ($contact->surname ?? ''));
        }

        $email = trim((string) ($contact?->email ?? ''));
        if ($email !== '' && str_ends_with(strtolower($email), '@chat.placeholder'))
        {
            $email = '';
        }

        return [
            'contact_id' => $contact !== null ? (int) $contact->id : null,
            'name' => $name,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
            'status_id' => $contact?->status_id !== null ? (int) $contact->status_id : null,
            'statuses' => $this->catalog($team)['statuses'],
            'user' => app(InboxContactAccessService::class)->presentForContact($team, $contact),
        ];
    }

    /**
     * Attach contacts-module categories without dropping tags other modules may have left.
     *
     * @param  list<int>  $categoryIds
     * @return array{contact_id: int|null, selected: list<array{id: int, name: string, color: string|null}>, available: list<array{id: int, name: string, color: string|null}>}
     */
    public function assign(Team $team, Contact $contact, array $categoryIds): array
    {
        $valid = $this->assignableIds($team, $categoryIds);
        $requested = $this->normalizedIds($categoryIds);

        if ($valid === [] || count($valid) !== count($requested))
        {
            throw new InvalidArgumentException('The selected category is invalid.');
        }

        $contact->categories()->syncWithoutDetaching($valid);

        return $this->present($team, $contact->fresh());
    }

    /**
     * Replace the contacts-module tags and leave any other module's pivot rows alone.
     * An empty list clears the contact tags.
     *
     * @param  list<int|string>  $categoryIds
     * @return array{contact_id: int|null, selected: list<array{id: int, name: string, color: string|null}>, available: list<array{id: int, name: string, color: string|null}>}
     */
    public function replace(Team $team, Contact $contact, array $categoryIds): array
    {
        $requested = $this->normalizedIds($categoryIds);
        $valid = $this->assignableIds($team, $categoryIds);
        if (count($valid) !== count($requested))
        {
            throw new InvalidArgumentException('The selected category is invalid.');
        }

        $keep = $this->idsOutsideContactsModule($contact);
        $contact->categories()->sync(array_values(array_unique(array_merge($keep, $valid))));

        return $this->present($team, $contact->fresh());
    }

    /**
     * @return array{statuses: list<array{id: int, name: string, color: string|null}>, categories: list<array{id: int, name: string, color: string|null}>}
     */
    public function catalog(?Team $team): array
    {
        if ($team === null)
        {
            return ['statuses' => [], 'categories' => []];
        }

        return [
            'statuses' => ContactStatus::query()
                ->where('name', '!=', 'Finalizado')
                ->orderBy('id')
                ->get(['id', 'name', 'label_class'])
                ->map(fn (ContactStatus $status): array => [
                    'id' => (int) $status->id,
                    'name' => (string) $status->name,
                    'color' => $this->statusTone($status->label_class),
                ])
                ->values()
                ->all(),
            'categories' => $this->availableFor($team),
        ];
    }

    private function statusTone(?string $labelClass): ?string
    {
        return match ($labelClass)
        {
            'bg-label-success' => '#28c76f',
            'bg-label-warning' => '#ffab00',
            'bg-label-info' => '#00cfe8',
            'bg-label-primary' => '#696cff',
            'bg-label-danger' => '#ea5455',
            'bg-label-dark' => '#4b4b4b',
            'bg-label-secondary' => '#82868b',
            default => null,
        };
    }

    /**
     * @return array{id: int, name: string, color: string|null}
     */
    public function findOrCreate(Team $team, string $name, ?string $color = null): array
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
        foreach ($this->availableFor($team) as $row)
        {
            if (mb_strtolower($row['name']) === $normalized)
            {
                return $row;
            }
        }

        $category = DatabaseSequence::retryOnDuplicateId('categories', function () use ($name, $moduleId, $team, $color)
        {
            return Category::query()->create([
                'name' => $name,
                'module_id' => $moduleId,
                'team_id' => $team->id,
                'parent_id' => null,
                'order' => $this->nextManualOrder($team, $moduleId),
                'status' => 1,
                'color' => self::normalizeColor($color),
            ]);
        });

        return $this->presentCategory($category);
    }

    /**
     * Team-owned contact categories for the settings manager.
     *
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
            ->map(fn (Category $category): array => array_merge(
                $this->presentCategory($category),
                ['contacts_count' => (int) $category->contacts_count],
            ))
            ->values()
            ->all();
    }

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

        $category = DatabaseSequence::retryOnDuplicateId('categories', function () use ($name, $moduleId, $team, $color)
        {
            return Category::query()->create([
                'name' => $name,
                'module_id' => $moduleId,
                'team_id' => $team->id,
                'parent_id' => null,
                'order' => $this->nextManualOrder($team, $moduleId),
                'status' => 1,
                'color' => self::normalizeColor($color),
            ]);
        });

        return array_merge($this->presentCategory($category), ['contacts_count' => 0]);
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

        return array_merge($this->presentCategory($owned->fresh()), ['contacts_count' => 0]);
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

    /**
     * @param  list<int|string>  $categoryIds
     * @return list<int>
     */
    public function assignableIds(Team $team, array $categoryIds): array
    {
        $available = [];
        foreach ($this->availableFor($team) as $row)
        {
            $available[$row['id']] = true;
        }

        $valid = [];
        foreach ($this->normalizedIds($categoryIds) as $id)
        {
            if (isset($available[$id]))
            {
                $valid[] = $id;
            }
        }

        return $valid;
    }

    /**
     * @return list<array{id: int, name: string, color: string|null}>
     */
    private function selectedFor(Team $team, Contact $contact): array
    {
        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            return [];
        }

        $query = $contact->categories()
            ->where('module_id', $moduleId)
            ->where('status', '>', 0)
            ->where(function ($query) use ($team)
            {
                $query->whereNull('categories.team_id')->orWhere('categories.team_id', $team->id);
            });

        $this->applyCategoryOrder($query, $team, 'categories');

        return $query
            ->get(['categories.id', 'categories.name', 'categories.color'])
            ->unique('id')
            ->values()
            ->map(fn (Category $category): array => $this->presentCategory($category))
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, color: string|null}>
     */
    private function availableFor(Team $team): array
    {
        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            return [];
        }

        $query = Category::query()
            ->where('module_id', $moduleId)
            ->where('status', '>', 0)
            ->where(function ($query) use ($team)
            {
                $query->whereNull('team_id')->orWhere('team_id', $team->id);
            });

        $this->applyCategoryOrder($query, $team);

        return $query
            ->get(['id', 'name', 'color'])
            ->map(fn (Category $category): array => $this->presentCategory($category))
            ->values()
            ->all();
    }

    private function applyCategoryOrder(mixed $query, Team $team, string $table = ''): void
    {
        $prefix = $table !== '' ? $table.'.' : '';

        if ($this->sortForTeam($team) === self::SORT_MANUAL)
        {
            $query
                ->orderByRaw("CASE WHEN {$prefix}team_id IS NULL THEN 1 ELSE 0 END")
                ->orderBy($prefix.'order')
                ->orderBy($prefix.'name');

            return;
        }

        $query->orderBy($prefix.'name');
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
     * @return array{id: int, name: string, color: string|null}
     */
    private function presentCategory(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'color' => self::normalizeColor($category->color),
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

    /**
     * @return list<int>
     */
    private function idsOutsideContactsModule(Contact $contact): array
    {
        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            return $contact->categories()->pluck('categories.id')->all();
        }

        return $contact->categories()
            ->where(function ($query) use ($moduleId)
            {
                $query->where('categories.module_id', '!=', $moduleId)
                    ->orWhereNull('categories.module_id');
            })
            ->pluck('categories.id')
            ->unique()
            ->values()
            ->all();
    }

    private function contactsModuleId(): ?int
    {
        $moduleId = Module::query()->where('key', 'contacts')->value('id');

        return $moduleId !== null ? (int) $moduleId : null;
    }
}
