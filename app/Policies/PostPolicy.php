<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(['admin', 'root']))
        {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    public function view(User $user, Post $post): bool
    {
        return $this->belongsToCurrentTeam($user, $post)
            && $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    public function update(User $user, Post $post): bool
    {
        return $this->belongsToCurrentTeam($user, $post)
            && $user->hasRole(['collaborator', 'developer', 'editor', 'technical']);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->belongsToCurrentTeam($user, $post)
            && $user->hasRole(['developer', 'editor', 'technical']);
    }

    public function restore(User $user, Post $post): bool
    {
        return false;
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return false;
    }

    private function belongsToCurrentTeam(User $user, Post $post): bool
    {
        $currentTeam = $user->currentTeam;

        return $currentTeam !== null && $post->team_id === $currentTeam->id;
    }
}
