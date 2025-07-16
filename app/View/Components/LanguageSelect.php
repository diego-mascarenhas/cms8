<?php

namespace App\View\Components;

use App\Models\Language;
use Illuminate\View\Component;

class LanguageSelect extends Component
{
	public $name;

	public $id;

	public $value;

	public $label;

	public function __construct($name = 'language', $id = null, $value = null, $label = 'Idioma')
	{
		$this->name = $name;
		$this->id = $id ?? $name;
		$this->value = $value ?? 'es';
		$this->label = $label;
	}

	public function render()
	{
		$languages = Language::orderBy('name')->get();

		return view('components.language-select', [
			'languages' => $languages,
		]);
	}
}
