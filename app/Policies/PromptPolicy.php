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
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Prompt $prompt): bool
    {
        return $user->hasRole('admin');
    }
}
