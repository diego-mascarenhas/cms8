<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Module;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ModuleCategoriesManagerModal extends Component
{
    public string $moduleKey = '';

    public string $linkedSelectId = '';

    public bool $show = false;

    public ?int $editingId = null;

    public string $editingName = '';

    public ?string $feedback = null;

    public ?string $feedbackType = null;

    public function mount(string $moduleKey = '', string $linkedSelectId = ''): void
    {
        $this->moduleKey = $moduleKey;
        $this->linkedSelectId = $linkedSelectId;
    }

    public function getModuleLabelProperty(): string
    {
        if ($this->moduleKey === '')
        {
            return '';
        }

        return Module::where('key', $this->moduleKey)->value('name') ?? $this->moduleKey;
    }

    /**
     * @return array<int, array{id: int, name: string, display: string, parent_id: ?int, can_manage: bool}>
     */
    public function getRowsProperty(): array
    {
        if ($this->moduleKey === '')
        {
            return [];
        }

        $module = Module::where('key', $this->moduleKey)->first();
        if (! $module)
        {
            return [];
        }

        $team = Auth::user()->currentTeam;
        $teamId = $team->id;

        $base = Category::query()
            ->where('module_id', $module->id)
            ->where('status', 1)
            ->where(function ($query) use ($teamId)
            {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $teamId);
            })
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $byId = $base->keyBy('id');

        $rows = [];
        foreach ($base as $category)
        {
            $display = $category->name;
            if ($category->parent_id && isset($byId[$category->parent_id]))
            {
                $display = $byId[$category->parent_id]->name.' › '.$category->name;
            }

            $rows[] = [
                'id' => $category->id,
                'name' => $category->name,
                'display' => $display,
                'parent_id' => $category->parent_id,
                'can_manage' => $category->team_id !== null && (int) $category->team_id === (int) $teamId,
            ];
        }

        usort($rows, function (array $a, array $b)
        {
            return strcasecmp($a['display'], $b['display']);
        });

        return $rows;
    }

    public function openModal(): void
    {
        Gate::authorize('viewAny', Category::class);
        $this->resetFeedback();
        $this->editingId = null;
        $this->editingName = '';
        $this->show = true;
    }

    public function closeModal(): void
    {
        $this->show = false;
        $this->editingId = null;
        $this->editingName = '';
        $this->resetFeedback();
    }

    public function startEdit(int $categoryId): void
    {
        $this->resetFeedback();
        $category = $this->resolveManagedCategory($categoryId);
        if (! $category)
        {
            return;
        }

        $this->editingId = $category->id;
        $this->editingName = $category->name;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingName = '';
        $this->resetFeedback();
    }

    public function saveEdit(): void
    {
        $this->resetFeedback();
        if ($this->editingId === null)
        {
            return;
        }

        $this->validate([
            'editingName' => 'required|string|min:2|max:255',
        ]);

        $category = $this->resolveManagedCategory($this->editingId);
        if (! $category)
        {
            return;
        }

        Gate::authorize('update', $category);

        $category->name = $this->editingName;
        $category->save();

        $this->editingId = null;
        $this->editingName = '';
        $this->setFeedback(__('Category updated.'), 'success');
        $this->dispatchCategoriesRefreshed();
    }

    public function deleteCategory(int $categoryId): void
    {
        $this->resetFeedback();
        $category = $this->resolveManagedCategory($categoryId);
        if (! $category)
        {
            return;
        }

        Gate::authorize('delete', $category);

        if ($category->children()->exists())
        {
            $this->setFeedback(__('Remove or reassign subcategories first.'), 'danger');

            return;
        }

        $usage = $category->blockingDeleteUsageCount();
        if ($usage > 0)
        {
            $this->setFeedback(__('This category is in use and cannot be deleted.'), 'danger');

            return;
        }

        $category->delete();

        if ($this->editingId === $categoryId)
        {
            $this->cancelEdit();
        }

        $this->setFeedback(__('Category deleted.'), 'success');
        $this->dispatchCategoriesRefreshed();
    }

    public function render(): View
    {
        return view('livewire.module-categories-manager-modal');
    }

    private function resolveManagedCategory(int $categoryId): ?Category
    {
        $team = Auth::user()->currentTeam;

        return Category::query()
            ->where('team_id', $team->id)
            ->where('id', $categoryId)
            ->first();
    }

    private function setFeedback(string $message, string $type): void
    {
        $this->feedback = $message;
        $this->feedbackType = $type;
    }

    private function resetFeedback(): void
    {
        $this->feedback = null;
        $this->feedbackType = null;
    }

    private function dispatchCategoriesRefreshed(): void
    {
        $this->dispatch('module-categories-refreshed', selectId: $this->linkedSelectId, moduleKey: $this->moduleKey);
    }
}
