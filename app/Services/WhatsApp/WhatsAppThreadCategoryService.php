<?php

namespace App\Services\WhatsApp;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Module;
use App\Models\Team;

/**
 * Contact categories as the inbox thread header shows them: only the ones the contacts module
 * offers, so a tag left by another module's import never surfaces next to the contact name.
 */
class WhatsAppThreadCategoryService
{
    /**
     * @return array{contact_id: int|null, selected: list<array{id: int, name: string}>}
     */
    public function present(?Team $team, ?Contact $contact): array
    {
        return [
            'contact_id' => $contact !== null ? (int) $contact->id : null,
            'selected' => $team !== null && $contact !== null ? $this->selectedFor($team, $contact) : [],
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function selectedFor(Team $team, Contact $contact): array
    {
        $moduleId = Module::query()->where('key', 'contacts')->value('id');
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
            ->map(fn (Category $category): array => ['id' => (int) $category->id, 'name' => (string) $category->name])
            ->values()
            ->all();
    }
}
