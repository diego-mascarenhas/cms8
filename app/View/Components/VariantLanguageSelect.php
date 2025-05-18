<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\Language;
use App\Models\LanguageVariant;

class VariantLanguageSelect extends Component
{
    public $name;
    public $id;
    public $value;
    public $label;
    public $baseLanguage;

    public function __construct($name = 'language_variant', $id = null, $value = null, $label = 'Variante de idioma', $baseLanguage = null)
    {
        $this->name = $name;
        $this->id = $id ?? $name;
        $this->value = $value;
        $this->label = $label;
        $this->baseLanguage = $baseLanguage;
    }

    public function render()
    {
        $languages = Language::orderBy('name')->get();
        $variants = collect();
        
        if ($this->baseLanguage) {
            $variants = LanguageVariant::getVariantsFor($this->baseLanguage);
        }
        
        return view('components.variant-language-select', [
            'languages' => $languages,
            'variants' => $variants
        ]);
    }
} 