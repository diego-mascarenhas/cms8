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

    public string $valueKey;

    public function __construct($name = 'country', $id = null, $value = null, $label = 'País', string $valueKey = 'id')
    {
        $this->name = $name;
        $this->id = $id ?? $name;
        $this->value = $value ?? ($valueKey === 'code' ? '' : 724);
        $this->label = $label;
        $this->valueKey = in_array($valueKey, ['id', 'code'], true) ? $valueKey : 'id';
    }

    public function render()
    {
        $countries = Country::orderBy('name')->get();

        return view('components.country-select', [
            'countries' => $countries,
            'valueKey' => $this->valueKey,
        ]);
    }
}
