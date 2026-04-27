<?php

namespace App\Policies;

use App\Models\TeamPassword;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPasswordPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('root'))
        {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, TeamPassword $password): bool
    {
        return $this->viewAny($user) && $password->team_id === $user->currentTeam?->id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TeamPassword $password): bool
    {
        return $this->view($user, $password);
    }

    public function delete(User $user, TeamPassword $password): bool
    {
        return $this->view($user, $password);
    }
}
