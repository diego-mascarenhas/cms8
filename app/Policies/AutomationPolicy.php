<?php

namespace App\Policies;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AutomationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Automation $automation): bool
    {
        return $user->hasRole('admin') && $automation->team_id === $user->current_team_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Automation $automation): bool
    {
        return $user->hasRole('admin') && $automation->team_id === $user->current_team_id;
    }

    public function delete(User $user, Automation $automation): bool
    {
        return $user->hasRole('admin') && $automation->team_id === $user->current_team_id;
    }

    public function restore(User $user, Automation $automation): bool
    {
        return $user->hasRole('admin') && $automation->team_id === $user->current_team_id;
    }

    public function forceDelete(User $user, Automation $automation): bool
    {
        return $user->hasRole('admin') && $automation->team_id === $user->current_team_id;
    }
}
