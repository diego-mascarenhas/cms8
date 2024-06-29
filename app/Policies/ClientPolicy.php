<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Client;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Client $client)
    {
        return $user->id === $client->user_id;
    }

    public function manage(User $user, Client $client)
    {
        return $user->hasRole('Colaborador') && $user->id === $client->assigned_to;
    }
}
