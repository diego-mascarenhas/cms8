<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InvoicePolicy
{
	/**
	 * Perform pre-authorization checks.
	 * Admins have full access to everything in their team.
	 */
	public function before(User $user, string $ability): ?bool
	{
		if ($user->hasRole('admin')) {
			return true;
		}

		return null;  // Continue to specific policy methods
	}

	/**
	 * Determine whether the user can view any models.
	 */
	public function viewAny(User $user): bool
	{
		if ($user->hasRole(['admin', 'collaborator', 'client']))
		{
			return true;
		}

		return false;
	}

	/**
	 * Determine whether the user can view the model.
	 */
	public function view(User $user, Invoice $invoice): bool
	{
		// Admin can see all invoices in their team
		if ($user->hasRole('admin'))
		{
			return $invoice->team_id === $user->currentTeam->id;
		}

		// Collaborator can see invoices of enterprises they are responsible for
		if ($user->hasRole('collaborator'))
		{
			if (!$invoice->enterprise)
			{
				return false;
			}

			return $invoice->enterprise->responsible_id === $user->id && $invoice->team_id === $user->currentTeam->id;
		}

		// Client can see invoices of their enterprises
		if ($user->hasRole('client'))
		{
			// Get user's contact
			$contact = $user->contact;
			if (!$contact)
			{
				return false;
			}

			// Check if invoice belongs to any of the contact's enterprises
			$enterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();

			return in_array($invoice->enterprise_id, $enterpriseIds) && $invoice->team_id === $user->currentTeam->id;
		}

		return false;
	}

	/**
	 * Determine whether the user can create models.
	 */
	public function create(User $user): bool
	{
		return $user->hasRole('admin');
	}

	/**
	 * Determine whether the user can update the model.
	 */
	public function update(User $user, Invoice $invoice): bool
	{
		// Only admin can update invoices
		return $user->hasRole('admin');
	}

	/**
	 * Determine whether the user can delete the model.
	 */
	public function delete(User $user, Invoice $invoice): bool
	{
		return $user->hasRole('admin');
	}

	/**
	 * Determine whether the user can restore the model.
	 */
	public function restore(User $user, Invoice $invoice): bool
	{
		return $user->hasRole('admin');
	}

	/**
	 * Determine whether the user can permanently delete the model.
	 */
	public function forceDelete(User $user, Invoice $invoice): bool
	{
		return $user->hasRole('admin');
	}

	/**
	 * Get query filter for role-based access
	 */
	public static function getQueryFilter(User $user): \Closure
	{
		return function (Builder $query) use ($user) {
			// Admin can see all invoices in their team
			if ($user->hasRole('admin'))
			{
				return $query->where('team_id', $user->currentTeam->id);
			}

			// Collaborator can see invoices of enterprises they are responsible for
			if ($user->hasRole('collaborator'))
			{
				return $query->where('team_id', $user->currentTeam->id)
					->whereHas('enterprise', function ($q) use ($user)
					{
						$q->where('responsible_id', $user->id);
					});
			}

			// Client can see invoices of their enterprises
			if ($user->hasRole('client'))
			{
				// Get user's contact
				$contact = $user->contact;
				if (!$contact)
				{
					return $query->whereRaw('1 = 0'); // Return no results
				}

				// Check if invoice belongs to any of the contact's enterprises
				$enterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();

				return $query->where('team_id', $user->currentTeam->id)
					->whereIn('enterprise_id', $enterpriseIds);
			}

			// Other roles have no access
			return $query->whereRaw('1 = 0');
		};
	}
}
