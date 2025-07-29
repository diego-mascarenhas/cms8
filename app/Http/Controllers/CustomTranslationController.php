<?php

namespace App\Http\Controllers;

use App\Services\CustomTranslationService;
use Illuminate\Http\Request;

class CustomTranslationController extends Controller
{
	protected $translationService;

	public function __construct(CustomTranslationService $translationService)
	{
		$this->translationService = $translationService;
	}

	/**
	 * Show the custom translations management page
	 */
	public function index()
	{
		$translations = $this->translationService->getAll();
		$currentLocale = app()->getLocale();

		return view('custom-translations.index', compact('translations', 'currentLocale'));
	}

	/**
	 * Store a new custom translation
	 */
	public function store(Request $request)
	{
		$request->validate([
			'key' => 'required|string|max:255',
			'value' => 'required|string',
			'group' => 'required|string|max:50',
			'locale' => 'required|string|max:5',
		]);

		$this->translationService->set(
			$request->key,
			$request->value,
			$request->group,
			$request->locale
		);

		return redirect()->back()->with('success', 'Translation saved successfully!');
	}

	/**
	 * Update an existing custom translation
	 */
	public function update(Request $request, $id)
	{
		$request->validate([
			'value' => 'required|string',
		]);

		// Get the translation to update
		$translation = \App\Models\CustomTranslation::findOrFail($id);

		$this->translationService->set(
			$translation->key,
			$request->value,
			$translation->group,
			$translation->locale
		);

		return redirect()->back()->with('success', 'Translation updated successfully!');
	}

	/**
	 * Remove a custom translation
	 */
	public function destroy($id)
	{
		$translation = \App\Models\CustomTranslation::findOrFail($id);

		$this->translationService->remove(
			$translation->key,
			$translation->group,
			$translation->locale
		);

		return redirect()->back()->with('success', 'Translation removed successfully!');
	}

	/**
	 * Example of how to use custom translations
	 */
	public function example()
	{
		// Example: Set a custom welcome message
		$this->translationService->set('welcome', '¡Bienvenida a bbo!', 'app', 'es');

		// Example: Set a custom button text
		$this->translationService->set('save', 'Guardar cambios', 'app', 'es');

		return redirect()->back()->with('success', 'Example translations created!');
	}
}
