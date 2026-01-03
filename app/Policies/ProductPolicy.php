<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductPolicy
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
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        // Admin can see all products
        if ($user->hasRole('admin'))
        {
            return true;
        }

        // Collaborators can see products
        if ($user->hasRole('collaborator'))
        {
            return true;
        }

        // Developers and editors can view products
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        // Admin can see any product in their team
        if ($user->hasRole('admin'))
        {
            return $product->team_id === $user->currentTeam->id;
        }

        // Collaborators can view products in their team
        if ($user->hasRole('collaborator'))
        {
            return $product->team_id === $user->currentTeam->id;
        }

        // Developers and editors can view products in their team
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return $product->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'collaborator', 'developer', 'technical']);
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        // Admin can update any product in their team
        if ($user->hasRole('admin'))
        {
            return $product->team_id === $user->currentTeam->id;
        }

        // Collaborators can update products in their team
        if ($user->hasRole('collaborator'))
        {
            return $product->team_id === $user->currentTeam->id;
        }

        // Developers and technical users can update products in their team
        if ($user->hasRole(['developer', 'technical']))
        {
            return $product->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        // Only admins can delete products
        return $user->hasRole('admin') && $product->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can restore the product.
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->hasRole('admin') && $product->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can permanently delete the product.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasRole('admin') && $product->team_id === $user->currentTeam->id;
    }
}
