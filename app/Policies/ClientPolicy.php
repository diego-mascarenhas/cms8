<?php

namespace App\Policies;

use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
	use HandlesAuthorization;

	public function view(User $user, Enterprise $client)
	{
		if ($user->hasRole('admin')) {
			return true;
		}

		if ($user->hasRole('collaborator')) {
			return $client->assigned_to == $user->id;
		}

		return false;
	}

	public function manage(User $user, Enterprise $client)
	{
		return $user->hasRole('collaborator') && $client->assigned_to == $user->id;
	}
}
