<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\Country;

class CountrySelect extends Component
{
    public $name;
    public $id;
    public $selected;
    public $label;

    public function __construct($name = 'country', $id = null, $selected = null, $label = 'País')
    {
        $this->name = $name;
        $this->id = $id ?? $name;
        $this->selected = $selected;
        $this->label = $label;
    }

    public function render()
    {
        $countries = Country::orderBy('name')->get();
        return view('components.country-select', [
            'countries' => $countries,
        ]);
    }
}
