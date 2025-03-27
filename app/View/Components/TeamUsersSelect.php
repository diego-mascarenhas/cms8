<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\User;

class TeamUsersSelect extends Component
{
    public $options;
    public $selected;
    public $label;
    public $id;

    public function __construct($selected = null, $label = 'Team Member', $id = 'user_id')
    {
        $this->selected = $selected;
        $this->label = $label;
        $this->id = $id;
        $this->options = $this->getTeamUsers();
    }

    private function getTeamUsers()
    {
        return User::whereHas('teams', function($query) {
            $query->where('team_id', auth()->user()->currentTeam->id);
        })->pluck('name', 'id');
    }

    public function render()
    {
        return view('components.team-users-select');
    }
} 