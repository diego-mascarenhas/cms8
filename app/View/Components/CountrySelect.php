<?php

namespace App\View\Components;

use App\Models\Country;
use Illuminate\View\Component;

class CountrySelect extends Component
{
    public $name;

    public $id;

    public $value;

    public $label;

    public function __construct($name = 'country', $id = null, $value = null, $label = 'País')
    {
        $this->name = $name;
        $this->id = $id ?? $name;
        $this->value = $value ?? 724;
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
