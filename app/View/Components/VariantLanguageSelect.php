<?php

namespace App\View\Components;

use App\Models\Language;
use App\Models\LanguageVariant;
use Illuminate\View\Component;

class VariantLanguageSelect extends Component
{
	public $name;

	public $id;

	public $value;

	public $label;

	public $baseLanguage;

	public $required;

	public $showBaseLanguages;

	public function __construct($name = 'language_variant', $id = null, $value = null, $label = 'Variante de idioma', $baseLanguage = null, $required = false, $showBaseLanguages = false)
	{
		$this->name = $name;
		$this->id = $id ?? $name;
		$this->value = $value;
		$this->label = $label;
		$this->baseLanguage = $baseLanguage;
		$this->required = $required;
		$this->showBaseLanguages = $showBaseLanguages;
	}

	public function render()
	{
		// Get base languages with more than one variant
		$baseCodes = LanguageVariant::select('base_language')
			->groupBy('base_language')
			->havingRaw('COUNT(*) > 1')
			->pluck('base_language');

		$baseLanguages = Language::whereIn('code', $baseCodes)->get();
		$variants = LanguageVariant::with('baseLanguage')->get();

		// Merge base languages and variants into a single options collection
		$options = collect();
		if ($this->showBaseLanguages)
		{
			foreach ($baseLanguages as $base)
			{
				$options->push((object) [
					'value' => $base->code,
					'label' => $base->name . '  (Todos)',
				]);
			}
		}
		foreach ($variants as $variant)
		{
			$options->push((object) [
				'value' => $variant->code,
				'label' => $variant->name,
			]);
		}
		// Sort alphabetically by label
		$options = $options->sortBy('label')->values();

		return view('components.variant-language-select', [
			'options' => $options,
		]);
	}
}
