<?php

namespace App\Services\WhatsApp;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\Team;
use App\Support\DatabaseSequence;
use InvalidArgumentException;

/**
 * Contact categories as the inbox thread header shows them: only the ones the contacts module
 * offers, so a tag left by another module's import never surfaces next to the contact name.
 */
class WhatsAppThreadCategoryService
{
    /**
     * @return array{contact_id: int|null, selected: list<array{id: int, name: string}>, available: list<array{id: int, name: string}>}
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
     * Attach contacts-module categories without dropping tags other modules may have left.
     *
     * @param  list<int>  $categoryIds
     * @return array{contact_id: int|null, selected: list<array{id: int, name: string}>, available: list<array{id: int, name: string}>}
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
     * @return array{contact_id: int|null, selected: list<array{id: int, name: string}>, available: list<array{id: int, name: string}>}
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
     * @return array{statuses: list<array{id: int, name: string}>, categories: list<array{id: int, name: string}>}
     */
    public function catalog(?Team $team): array
    {
        if ($team === null)
        {
            return ['statuses' => [], 'categories' => []];
        }

        return [
            'statuses' => ContactStatus::query()
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (ContactStatus $status): array => [
                    'id' => (int) $status->id,
                    'name' => (string) $status->name,
                ])
                ->values()
                ->all(),
            'categories' => $this->availableFor($team),
        ];
    }

    /**
     * @return array{id: int, name: string}
     */
    public function findOrCreate(Team $team, string $name): array
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

        $category = DatabaseSequence::retryOnDuplicateId('categories', function () use ($name, $moduleId, $team)
        {
            return Category::query()->create([
                'name' => $name,
                'module_id' => $moduleId,
                'team_id' => $team->id,
                'parent_id' => null,
                'order' => 0,
                'status' => 1,
            ]);
        });

        return $this->presentCategory($category);
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
     * @return list<array{id: int, name: string}>
     */
    private function selectedFor(Team $team, Contact $contact): array
    {
        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            return [];
        }

        return $contact->categories()
            ->where('module_id', $moduleId)
            ->where('status', '>', 0)
            ->where(function ($query) use ($team)
            {
                $query->whereNull('categories.team_id')->orWhere('categories.team_id', $team->id);
            })
            ->orderBy('name')
            ->get(['categories.id', 'categories.name'])
            ->unique('id')
            ->values()
            ->map(fn (Category $category): array => $this->presentCategory($category))
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function availableFor(Team $team): array
    {
        $moduleId = $this->contactsModuleId();
        if ($moduleId === null)
        {
            return [];
        }

        return Category::query()
            ->where('module_id', $moduleId)
            ->where('status', '>', 0)
            ->where(function ($query) use ($team)
            {
                $query->whereNull('team_id')->orWhere('team_id', $team->id);
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => $this->presentCategory($category))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string}
     */
    private function presentCategory(Category $category): array
    {
        return ['id' => (int) $category->id, 'name' => (string) $category->name];
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
