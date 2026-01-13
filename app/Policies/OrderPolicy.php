<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Admins have full access to everything in their team.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return null; // Continue to specific policy methods
    }

    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        // Admin can see all orders
        if ($user->hasRole('admin'))
        {
            return true;
        }

        // Collaborators can see orders
        if ($user->hasRole('collaborator'))
        {
            return true;
        }

        // Developers and editors can view orders
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        // Admin can see any order in their team
        if ($user->hasRole('admin'))
        {
            return $order->team_id === $user->currentTeam->id;
        }

        // Collaborators can view orders in their team
        if ($user->hasRole('collaborator'))
        {
            return $order->team_id === $user->currentTeam->id;
        }

        // Developers and editors can view orders in their team
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return $order->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'collaborator', 'developer', 'technical']);
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        // Admin can update any order in their team
        if ($user->hasRole('admin'))
        {
            return $order->team_id === $user->currentTeam->id;
        }

        // Collaborators can update orders in their team
        if ($user->hasRole('collaborator'))
        {
            return $order->team_id === $user->currentTeam->id;
        }

        // Developers and technical users can update orders in their team
        if ($user->hasRole(['developer', 'technical']))
        {
            return $order->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        // Only admins can delete orders
        return $user->hasRole('admin') && $order->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can restore the order.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->hasRole('admin') && $order->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can permanently delete the order.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasRole('admin') && $order->team_id === $user->currentTeam->id;
    }
}
