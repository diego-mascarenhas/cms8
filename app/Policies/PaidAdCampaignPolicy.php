<?php

namespace App\Policies;

use App\Models\PaidAdCampaign;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaidAdCampaignPolicy
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

    public function view(User $user, PaidAdCampaign $campaign): bool
    {
        return $this->belongsToTeam($user, $campaign)
            && $user->hasAnyRole(['collaborator', 'developer', 'technical', 'editor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['collaborator', 'developer', 'technical']);
    }

    public function update(User $user, PaidAdCampaign $campaign): bool
    {
        return $this->belongsToTeam($user, $campaign)
            && $user->hasAnyRole(['collaborator', 'developer', 'technical']);
    }

    public function delete(User $user, PaidAdCampaign $campaign): bool
    {
        return $this->belongsToTeam($user, $campaign)
            && $user->hasAnyRole(['collaborator', 'developer', 'technical']);
    }

    public function publish(User $user, PaidAdCampaign $campaign): bool
    {
        return $this->belongsToTeam($user, $campaign)
            && $user->hasAnyRole(['collaborator', 'developer', 'technical']);
    }

    private function belongsToTeam(User $user, PaidAdCampaign $campaign): bool
    {
        return $campaign->team_id === $user->currentTeam?->id;
    }
}
