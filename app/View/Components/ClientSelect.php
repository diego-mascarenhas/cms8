<?php

namespace App\View\Components;

use App\Models\Enterprise;
use Illuminate\View\Component;

class ClientSelect extends Component
{
    public $options;
    public $selected;
    public $label;
    public $id;
    public $allowNull;

    public function __construct($selected = null, $label = 'Cliente', $id = 'enterprise_id', $allowNull = true)
    {
        $this->selected = $selected;
        $this->label = $label;
        $this->id = $id;
        $this->allowNull = $allowNull;
        $this->options = $this->getClients();
    }

    private function getClients()
    {
        // Only get clients (type_id = 1)
        return Enterprise::where('type_id', 1)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function render()
    {
        return view('components.client-select');
    }
}
