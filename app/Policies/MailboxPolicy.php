<?php

namespace App\Policies;

use App\Models\Mailbox;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MailboxPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any mailboxes for the team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the mailbox.
     */
    public function view(User $user, Mailbox $mailbox): bool
    {
        return $mailbox->team_id === $user->currentTeam?->id && $user->belongsToTeam($mailbox->team);
    }

    /**
     * Determine whether the user can create mailboxes for the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can update the mailbox.
     */
    public function update(User $user, Mailbox $mailbox): bool
    {
        return $mailbox->team_id === $user->currentTeam?->id && $user->belongsToTeam($mailbox->team);
    }

    /**
     * Determine whether the user can delete the mailbox.
     */
    public function delete(User $user, Mailbox $mailbox): bool
    {
        return $mailbox->team_id === $user->currentTeam?->id && $user->belongsToTeam($mailbox->team);
    }
}
