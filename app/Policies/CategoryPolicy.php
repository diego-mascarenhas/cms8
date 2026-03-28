<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return $user->can('category.list');
    }

    public function update(User $user, Category $category): bool
    {
        $team = $user->currentTeam;
        if (! $team || (int) $category->team_id !== (int) $team->id)
        {
            return false;
        }

        if ($user->hasRole('admin'))
        {
            return true;
        }

        return $user->can('category.list');
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }
}
