<?php

namespace App\Policies;

use App\Models\Mailbox;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MailboxPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Mailbox $mailbox): bool
    {
        if (! $this->sameTeam($user, $mailbox))
        {
            return false;
        }

        return $mailbox->isTeamShared() || (int) $mailbox->user_id === (int) $user->id;
    }

    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function update(User $user, Mailbox $mailbox): bool
    {
        if (! $this->sameTeam($user, $mailbox))
        {
            return false;
        }

        if ($mailbox->isPersonal())
        {
            return (int) $mailbox->user_id === (int) $user->id;
        }

        return true;
    }

    public function delete(User $user, Mailbox $mailbox): bool
    {
        return $this->update($user, $mailbox);
    }

    private function sameTeam(User $user, Mailbox $mailbox): bool
    {
        return $mailbox->team_id === $user->currentTeam?->id && $user->belongsToTeam($mailbox->team);
    }
}
