<?php

namespace App\Policies;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PromptPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin') && $prompt->team_id === $user->current_team_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin') && $prompt->team_id === $user->current_team_id;
    }

    public function delete(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin') && $prompt->team_id === $user->current_team_id;
    }

    public function restore(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin') && $prompt->team_id === $user->current_team_id;
    }

    public function forceDelete(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin') && $prompt->team_id === $user->current_team_id;
    }
}
