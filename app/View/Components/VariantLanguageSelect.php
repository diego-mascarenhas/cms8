<?php

namespace App\View\Components;

use App\Models\Language;
use App\Models\LanguageVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;

class VariantLanguageSelect extends Component
{
    public $name;

    public $id;

    public $value;

    public $label;

    public $baseLanguage;

    public $required;

    public function __construct($name = 'language_variant', $id = null, $value = null, $label = 'Variante de idioma', $baseLanguage = null, $required = false)
    {
        $this->name = $name;
        $this->id = $id ?? $name;
        $this->value = $value;
        $this->label = $label;
        $this->baseLanguage = $baseLanguage;
        $this->required = $required;
    }

    public function render()
    {
        // Get all base_language codes with more than one variant
        $baseCodes = LanguageVariant::select('base_language')
            ->groupBy('base_language')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('base_language');

        // Retrieve Language models for those codes
        $baseLanguages = Language::whereIn('code', $baseCodes)->orderBy('name')->get();

        // Get all variants, eager load baseLanguage relation
        $variants = LanguageVariant::with('baseLanguage')->orderBy('name')->get();

        return view('components.variant-language-select', [
            'baseLanguages' => $baseLanguages,
            'variants' => $variants,
        ]);
    }
}
