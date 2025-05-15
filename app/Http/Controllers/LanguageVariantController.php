<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\LanguageVariant;
use Illuminate\Http\Request;

class LanguageVariantController extends Controller
{
    /**
     * Mostrar la lista de variantes de idioma
     */
    public function index()
    {
        $languages = Language::orderBy('name')->get();
        $variants = LanguageVariant::orderBy('base_language')->orderBy('name')->get();
        
        return view('language.variants.index', compact('languages', 'variants'));
    }
    
    /**
     * Mostrar el formulario para crear una variante
     */
    public function create()
    {
        $languages = Language::orderBy('name')->get();
        
        return view('language.variants.create', compact('languages'));
    }
    
    /**
     * Almacenar una nueva variante de idioma
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:language_variants',
            'name' => 'required|string|max:255',
            'base_language' => 'required|string|exists:languages,code',
            'country_code' => 'nullable|string|max:2',
            'native_name' => 'nullable|string|max:255',
            'flag' => 'nullable|string|max:2',
        ]);
        
        LanguageVariant::create($validated);
        
        return redirect()->route('language-variants.index')
            ->with('success', 'Variante de idioma creada correctamente');
    }
    
    /**
     * Obtener variantes para un idioma base (AJAX)
     */
    public function getVariants($baseLanguage)
    {
        $variants = LanguageVariant::getVariantsFor($baseLanguage);
        
        return response()->json($variants);
    }
} 