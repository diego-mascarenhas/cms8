<?php

namespace App\View\Components;

use App\Models\Fare;
use Illuminate\View\Component;
use Illuminate\Support\Facades\Auth;

class FareSelect extends Component
{
    public $id;
    public $name;
    public $label;
    public $required;
    public $placeholder;
    public $selected;
    public $fares;

    /**
     * Create a new component instance.
     *
     * @param string $id
     * @param string $name
     * @param string $label
     * @param bool $required
     * @param string $placeholder
     * @param array $selected
     * @return void
     */
    public function __construct(
        string $id,
        string $name = 'fare_ids[]',
        string $label = 'Servicios',
        bool $required = false,
        string $placeholder = 'Seleccione servicios',
        $selected = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->label = $label;
        $this->required = $required;
        $this->placeholder = $placeholder;
        $this->selected = is_array($selected) ? $selected : [$selected];
        $this->fares = $this->getFaresFromDatabase();
    }

    /**
     * Get fares from database grouped by type
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getFaresFromDatabase()
    {
        return Fare::with('type')
            ->where(function($query) {
                $query->whereNull('team_id')
                    ->orWhere('team_id', Auth::user()->currentTeam->id);
            })
            ->orderBy('name')
            ->get()
            ->groupBy(function($fare) {
                return $fare->type ? $fare->type->name : 'Sin categoría';
            });
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.fare-select');
    }
} 