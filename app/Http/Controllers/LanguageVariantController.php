<?php

namespace App\Http\Controllers;

use App\DataTables\LanguageVariantDataTable;
use App\Models\Language;
use App\Models\LanguageVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LanguageVariantController extends Controller
{
    /**
     * Display the list of language variants
     */
    public function index(LanguageVariantDataTable $dataTable)
    {
        if (Gate::denies('view-language-variants'))
        {
            return redirect()->route('403');
        }

        return $dataTable->render('language.variants.index');
    }

    /**
     * Show the form for creating a new language variant
     */
    public function create()
    {
        $languages = Language::orderBy('name')->get();

        return view('language.variants.form', compact('languages'));
    }

    /**
     * Store a new language variant
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:language_variants,code,NULL,id,team_id,'.auth()->user()->currentTeam->id,
            'name' => 'required|string|max:255',
            'base_language' => 'required|string|exists:languages,code',
            'country_code' => 'required|string|max:2',
        ]);

        $validated['team_id'] = auth()->user()->currentTeam->id;

        LanguageVariant::create($validated);

        return redirect()->route('language-variants.index')
            ->with('success', 'Variante de idioma creada correctamente');
    }

    /**
     * Show the form for editing a language variant
     */
    public function edit(LanguageVariant $languageVariant)
    {
        $languages = Language::orderBy('name')->get();
        $variant = $languageVariant;

        return view('language.variants.form', compact('variant', 'languages'));
    }

    /**
     * Update the language variant
     */
    public function update(Request $request, LanguageVariant $languageVariant)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:language_variants,code,'.$languageVariant->id.',id,team_id,'.auth()->user()->currentTeam->id,
            'name' => 'required|string|max:255',
            'base_language' => 'required|string|exists:languages,code',
            'country_code' => 'required|string|max:2',
        ]);

        $languageVariant->update($validated);

        return redirect()->route('language-variants.index')
            ->with('success', 'Variante de idioma actualizada correctamente');
    }

    /**
     * Delete the language variant
     */
    public function destroy(LanguageVariant $languageVariant)
    {
        $languageVariant->delete();

        if (request()->ajax())
        {
            return response()->json(['success' => true]);
        }

        return redirect()->route('language-variants.index')
            ->with('success', 'Variante de idioma eliminada correctamente');
    }

    /**
     * Get variants for a base language (AJAX)
     */
    public function getVariants($baseLanguage)
    {
        $variants = LanguageVariant::getVariantsFor($baseLanguage);

        return response()->json($variants);
    }
}
