<?php

namespace App\Policies;

use App\Models\Software;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SoftwarePolicy
{
	use HandlesAuthorization;

	/**
	 * Determine whether the user can view any software.
	 */
	public function viewAny(User $user)
	{
		// Allow admin, collaborator, and client roles to view software
		return $user->hasRole(['admin', 'collaborator', 'client']);
	}

	/**
	 * Determine whether the user can view the software.
	 */
	public function view(User $user, Software $software)
	{
		return $user->hasAnyRole(['admin', 'collaborator', 'client']) &&
			$user->currentTeam->id === $software->team_id;
	}

	/**
	 * Determine whether the user can create software.
	 */
	public function create(User $user)
	{
		// Allow admin and collaborator to create software
		return $user->hasRole(['admin', 'collaborator']);
	}

	/**
	 * Determine whether the user can update the software.
	 */
	public function update(User $user, Software $software)
	{
		// Admin can update any software in their team
		if ($user->hasRole('admin') && $user->currentTeam->id === $software->team_id)
		{
			return true;
		}

		// Collaborator can only update their own software within their team
		if ($user->hasRole('collaborator') &&
			$user->currentTeam->id === $software->team_id &&
			$software->user_id === $user->id)
		{
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the user can delete the software.
	 */
	public function delete(User $user, Software $software)
	{
		// Admin can delete any software in their team
		if ($user->hasRole('admin') && $user->currentTeam->id === $software->team_id)
		{
			return true;
		}

		// Collaborator can only delete their own software within their team
		if ($user->hasRole('collaborator') &&
			$user->currentTeam->id === $software->team_id &&
			$software->user_id === $user->id)
		{
			return true;
		}

		return false;
	}

	/**
	 * Get the query filter for the user's role.
	 */
	public function getQueryFilter(User $user)
	{
		if ($user->hasRole('admin'))
		{
			// Admin can see all software from their team (already filtered by global scope)
			return null;
		}

		if ($user->hasRole(['collaborator', 'client']))
		{
			// Collaborators and clients see software from their team (already filtered by global scope)
			return null;
		}

		// Default: no access
		return function ($query)
		{
			return $query->whereRaw('1 = 0'); // No results
		};
	}
}
