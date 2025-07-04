<?php

namespace App\Policies;

use App\Models\LanguageVariant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LanguageVariantPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any language variants.
     */
    public function viewAny(User $user)
    {
        // Allow admin, collaborator, and client roles to view language variants
        return $user->hasRole(['admin', 'collaborator', 'client']);
    }

    /**
     * Determine whether the user can view the language variant.
     */
    public function view(User $user, LanguageVariant $languageVariant)
    {
        // Allow admin, collaborator, and client roles to view language variants
        return $user->hasRole(['admin', 'collaborator', 'client']);
    }

    /**
     * Determine whether the user can create language variants.
     */
    public function create(User $user)
    {
        // Only admin can create language variants
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the language variant.
     */
    public function update(User $user, LanguageVariant $languageVariant)
    {
        // Only admin can update language variants
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the language variant.
     */
    public function delete(User $user, LanguageVariant $languageVariant)
    {
        // Only admin can delete language variants
        return $user->hasRole('admin');
    }
} 