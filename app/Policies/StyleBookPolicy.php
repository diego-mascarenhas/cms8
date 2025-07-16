<?php

namespace App\Policies;

use App\Models\StyleBook;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StyleBookPolicy
{
	use HandlesAuthorization;

	/**
	 * Determine whether the user can view any stylebooks.
	 */
	public function viewAny(User $user): bool
	{
		return $user->hasAnyRole(['admin', 'collaborator', 'client']);
	}

	/**
	 * Determine whether the user can view the stylebook.
	 */
	public function view(User $user, StyleBook $styleBook): bool
	{
		return $user->hasAnyRole(['admin', 'collaborator', 'client']) &&
			$user->currentTeam->id === $styleBook->team_id;
	}

	/**
	 * Determine whether the user can create stylebooks.
	 */
	public function create(User $user): bool
	{
		return $user->hasAnyRole(['admin', 'collaborator']);
	}

	/**
	 * Determine whether the user can update the stylebook.
	 */
	public function update(User $user, StyleBook $styleBook): bool
	{
		// Admin can update any stylebook in their team
		if ($user->hasRole('admin') && $user->currentTeam->id === $styleBook->team_id)
		{
			return true;
		}

		// Collaborator can only update their own stylebooks within their team
		if ($user->hasRole('collaborator') &&
			$user->currentTeam->id === $styleBook->team_id &&
			$styleBook->user_id === $user->id)
		{
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the user can delete the stylebook.
	 */
	public function delete(User $user, StyleBook $styleBook): bool
	{
		// Admin can delete any stylebook in their team
		if ($user->hasRole('admin') && $user->currentTeam->id === $styleBook->team_id)
		{
			return true;
		}

		// Collaborator can only delete their own stylebooks within their team
		if ($user->hasRole('collaborator') &&
			$user->currentTeam->id === $styleBook->team_id &&
			$styleBook->user_id === $user->id)
		{
			return true;
		}

		return false;
	}
}
