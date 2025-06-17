<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\Language;
use App\Models\LanguageVariant;
use Illuminate\Support\Facades\Log;

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
        $languages = Language::orderBy('name')->get();
        
        // Get all language variants, regardless of base language
        $variants = LanguageVariant::orderBy('name')->get();
        
        // Log for debugging
        Log::info('VariantLanguageSelect: Found ' . $variants->count() . ' language variants');
        
        return view('components.variant-language-select', [
            'languages' => $languages,
            'variants' => $variants
        ]);
    }
} 