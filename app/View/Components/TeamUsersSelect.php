<?php

namespace App\View\Components;

use App\Support\AssignableTeamUsers;
use Illuminate\View\Component;

class TeamUsersSelect extends Component
{
    public $options;

    public $selected;

    public $label;

    public $id;

    public $name;

    public $role;

    public bool $showNull;

    public bool $compact;

    public bool $disabled;

    public function __construct(
        $selected = null,
        $label = 'Team Member',
        $id = 'user_id',
        $name = null,
        $role = null,
        bool $showNull = false,
        bool $compact = false,
        bool $disabled = false,
    ) {
        $this->selected = $selected;
        $this->label = $label;
        $this->id = $id;
        $this->name = $name ?? $id;
        $this->role = $role;
        $this->showNull = $showNull;
        $this->compact = $compact;
        $this->disabled = $disabled;
        $this->options = $this->getTeamUsers();
    }

    private function getTeamUsers()
    {
        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return collect();
        }

        // Specific role filter (legacy); otherwise staff assignable like projects.idoneo
        if ($this->role)
        {
            return $team->allUsers()
                ->filter(fn ($user) => $user->hasRole($this->role))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->pluck('name', 'id');
        }

        return AssignableTeamUsers::optionsForTeam($team);
    }

    public function render()
    {
        return view('components.team-users-select');
    }
}
