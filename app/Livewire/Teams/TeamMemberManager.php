<?php

namespace App\Livewire\Teams;

use Laravel\Jetstream\Http\Livewire\TeamMemberManager as JetstreamTeamMemberManager;
use Laravel\Jetstream\Jetstream;

class TeamMemberManager extends JetstreamTeamMemberManager
{
    public function mount($team): void
    {
        parent::mount($team);

        if (! empty($this->addTeamMemberForm['role']))
        {
            return;
        }

        $defaultRole = collect(Jetstream::$roles)->first(fn ($role) => $role->key === 'admin')
            ?? collect(Jetstream::$roles)->first();

        if ($defaultRole)
        {
            $this->addTeamMemberForm['role'] = $defaultRole->key;
        }
    }
}
