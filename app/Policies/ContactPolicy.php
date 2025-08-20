<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContactPolicy
{
	use HandlesAuthorization;

	/**
	 * Determine whether the user can view any contacts.
	 */
	public function viewAny(User $user): bool
	{
		// Admin can see all contacts
		if ($user->hasRole('admin'))
		{
			return true;
		}

		// Collaborators can see contacts (but filtered to their own)
		if ($user->hasRole('collaborator'))
		{
			return true;
		}

		// Clients can see their own contact information
		if ($user->hasRole('client'))
		{
			return true;
		}

		// Regular users need specific permission
		return $user->can('contact.index');
	}

	/**
	 * Determine whether the user can view the contact.
	 */
	public function view(User $user, Contact $contact): bool
	{
		// Admin can see any contact in their team
		if ($user->hasRole('admin'))
		{
			return $contact->team_id === $user->currentTeam->id;
		}

		// Collaborators can only see their own contact
		if ($user->hasRole('collaborator'))
		{
			return $contact->user_id === $user->id && $contact->team_id === $user->currentTeam->id;
		}

		// Clients can see their own contact or contacts from their enterprises
		if ($user->hasRole('client'))
		{
			// Can see their own contact
			if ($contact->user_id === $user->id && $contact->team_id === $user->currentTeam->id)
			{
				return true;
			}

			// Can see contacts from enterprises they belong to
			$userContact = $user->contact;
			if ($userContact)
			{
				$enterpriseIds = $userContact->enterprises()->pluck('enterprises.id')->toArray();
				$contactEnterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();

				return ! empty(array_intersect($enterpriseIds, $contactEnterpriseIds));
			}

			return false;
		}

		// Regular users need specific permission and team membership
		return $user->can('contact.show') && $contact->team_id === $user->currentTeam->id;
	}

	/**
	 * Determine whether the user can create contacts.
	 */
	public function create(User $user): bool
	{
		if ($user->hasRole('admin'))
		{
			return true;
		}

		return $user->can('contact.create');
	}

	/**
	 * Determine whether the user can update the contact.
	 */
	public function update(User $user, Contact $contact): bool
	{
		// Admin can update any contact in their team
		if ($user->hasRole('admin'))
		{
			return $contact->team_id === $user->currentTeam->id;
		}

		// Collaborators can only update their own contact
		if ($user->hasRole('collaborator'))
		{
			return $contact->user_id === $user->id && $contact->team_id === $user->currentTeam->id;
		}

		// Regular users need specific permission and team membership
		return $user->can('contact.update') && $contact->team_id === $user->currentTeam->id;
	}

	/**
	 * Determine whether the user can delete the contact.
	 */
	public function delete(User $user, Contact $contact): bool
	{
		// Only admins can delete contacts
		if ($user->hasRole('admin'))
		{
			return $contact->team_id === $user->currentTeam->id;
		}

		// Regular users need specific permission
		return $user->can('contact.destroy') && $contact->team_id === $user->currentTeam->id;
	}

	/**
	 * Get the query filter for the user's role.
	 */
	public static function getQueryFilter(User $user)
	{
		return function ($query) use ($user)
		{
			// Admin can see all contacts in their team
			if ($user->hasRole('admin'))
			{
				return $query->where('team_id', $user->currentTeam->id);
			}

			// Collaborators can only see their own contact
			if ($user->hasRole('collaborator'))
			{
				return $query->where('team_id', $user->currentTeam->id)
					->where('user_id', $user->id);
			}

			// Clients can see their own contact and contacts from their enterprises
			if ($user->hasRole('client'))
			{
				$userContact = $user->contact;
				if (! $userContact)
				{
					return $query->where('team_id', $user->currentTeam->id)
						->where('user_id', $user->id);
				}

				$enterpriseIds = $userContact->enterprises()->pluck('enterprises.id')->toArray();

				return $query->where('team_id', $user->currentTeam->id)
					->where(function ($q) use ($user, $enterpriseIds)
					{
						$q->where('user_id', $user->id)
						    ->orWhereHas('enterprises', function ($enterpriseQuery) use ($enterpriseIds)
						    {
						        $enterpriseQuery->whereIn('enterprises.id', $enterpriseIds);
						    });
					});
			}

			// Regular users can see all contacts if they have permission
			if ($user->can('contact.index'))
			{
				return $query->where('team_id', $user->currentTeam->id);
			}

			// No access
			return $query->whereRaw('1 = 0');
		};
	}
}
