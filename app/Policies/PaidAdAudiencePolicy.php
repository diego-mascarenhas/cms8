<?php

namespace App\Policies;

use App\Models\PaidAdAudience;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaidAdAudiencePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['collaborator', 'developer', 'technical', 'editor']);
    }

    public function view(User $user, PaidAdAudience $audience): bool
    {
        return $this->belongsToTeam($user, $audience)
            && $user->hasAnyRole(['collaborator', 'developer', 'technical', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['collaborator', 'developer', 'technical']);
    }

    public function update(User $user, PaidAdAudience $audience): bool
    {
        return $this->belongsToTeam($user, $audience)
            && $user->hasAnyRole(['collaborator', 'developer', 'technical']);
    }

    public function delete(User $user, PaidAdAudience $audience): bool
    {
        return $this->belongsToTeam($user, $audience)
            && $user->hasAnyRole(['collaborator', 'developer', 'technical']);
    }

    private function belongsToTeam(User $user, PaidAdAudience $audience): bool
    {
        return $audience->team_id === $user->currentTeam?->id;
    }
}
