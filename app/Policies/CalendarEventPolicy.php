<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CalendarEventPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['admin', 'root']))
        {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasTeamCalendarAccess($user);
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        return $this->belongsToCurrentTeam($user, $event)
            && $this->hasTeamCalendarAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->hasTeamCalendarAccess($user);
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $this->belongsToCurrentTeam($user, $event)
            && $this->hasTeamCalendarAccess($user);
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $this->belongsToCurrentTeam($user, $event)
            && $this->hasTeamCalendarAccess($user);
    }

    private function hasTeamCalendarAccess(User $user): bool
    {
        if (! $user->currentTeam)
        {
            return false;
        }

        return $user->hasRole([
            'admin',
            'editor',
            'collaborator',
            'developer',
            'technical',
            'client',
        ]);
    }

    private function belongsToCurrentTeam(User $user, CalendarEvent $event): bool
    {
        return $user->currentTeam
            && (int) $event->team_id === (int) $user->currentTeam->id;
    }
}
