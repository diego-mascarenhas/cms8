<?php

namespace App\View\Components;

use Illuminate\View\Component;

class TeamUsersSelect extends Component
{
    public $options;

    public $selected;

    public $label;

    public $id;

    public $role;

    public function __construct($selected = null, $label = 'Team Member', $id = 'user_id', $role = null)
    {
        $this->selected = $selected;
        $this->label = $label;
        $this->id = $id;
        $this->role = $role;
        $this->options = $this->getTeamUsers();
    }

    private function getTeamUsers()
    {
        // Get all users from the current team (includes owner and members)
        $teamUsers = auth()->user()->currentTeam->allUsers();

        // If a specific role is requested, filter by that role
        if ($this->role)
        {
            $teamUsers = $teamUsers->filter(function ($user)
            {
                return $user->hasRole($this->role);
            });
        }

        return $teamUsers->pluck('name', 'id');
    }

    public function render()
    {
        return view('components.team-users-select');
    }
}
