<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
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
        if (! $user->currentTeam)
        {
            return false;
        }

        return $user->hasRole(['admin', 'editor', 'collaborator', 'technical', 'developer']);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (! $user->currentTeam || $ticket->team_id !== $user->currentTeam->id)
        {
            return false;
        }

        if ($user->hasRole(['admin', 'editor', 'technical', 'developer']))
        {
            return true;
        }

        if ($user->hasRole('collaborator'))
        {
            return $ticket->user_id === $user->id || $ticket->assigned_to === $user->id;
        }

        return $ticket->user_id === $user->id || $ticket->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if (! $user->currentTeam || $ticket->team_id !== $user->currentTeam->id)
        {
            return false;
        }

        if ($user->hasRole(['admin', 'editor', 'technical', 'developer']))
        {
            return true;
        }

        return $ticket->assigned_to === $user->id || $ticket->user_id === $user->id;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        if (! $user->currentTeam || $ticket->team_id !== $user->currentTeam->id)
        {
            return false;
        }

        return $user->hasRole('admin') || $ticket->assigned_to === $user->id;
    }
}
