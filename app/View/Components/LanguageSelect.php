<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\Language;

class LanguageSelect extends Component
{
    public $name;
    public $id;
    public $selected;
    public $label;

    public function __construct($name = 'language', $id = null, $selected = null, $label = 'Idioma')
    {
        $this->name = $name;
        $this->id = $id ?? $name;
        $this->selected = $selected;
        $this->label = $label;
    }

    public function render()
    {
        $languages = Language::all();
        return view('components.language-select', [
            'languages' => $languages,
        ]);
    }
}