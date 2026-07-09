<?php

namespace App\Livewire\Teams;

use Illuminate\Support\Collection;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager as JetstreamTeamMemberManager;
use Laravel\Jetstream\Jetstream;

class TeamMemberManager extends JetstreamTeamMemberManager
{
    public string $roleFilter = 'admin';

    public string $search = '';

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

    /**
     * Team members filtered by the selected membership role.
     */
    public function getFilteredMembersProperty(): Collection
    {
        $query = $this->team->users()->orderBy('users.name');

        if ($this->roleFilter !== 'all')
        {
            $query->wherePivot('role', $this->roleFilter);
        }

        $search = trim($this->search);

        if ($search !== '')
        {
            $query->where(function ($builder) use ($search)
            {
                $builder->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Total number of members in the team, ignoring the active filter.
     */
    public function getTotalMembersCountProperty(): int
    {
        return $this->team->users()->count();
    }

    /**
     * Options for the role filter dropdown.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Jetstream\Role>
     */
    public function getRoleFilterOptionsProperty(): Collection
    {
        return collect(Jetstream::$roles);
    }
}
