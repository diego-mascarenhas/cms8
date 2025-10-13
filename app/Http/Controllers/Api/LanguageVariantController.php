<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LanguageVariant;
use Illuminate\Http\Request;

class LanguageVariantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Authorize the request
        $this->authorize('viewAny', LanguageVariant::class);

        // Get all language variants
        $languageVariants = LanguageVariant::with('baseLanguage')
            ->orderBy('base_language')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $languageVariants,
            'count' => $languageVariants->count(),
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Authorize the request
        $this->authorize('create', LanguageVariant::class);

        // Validate the request
        $request->validate([
            'code' => 'required|string|max:10|unique:language_variants,code,NULL,id,team_id,'.auth()->user()->currentTeam->id,
            'name' => 'required|string|max:255',
            'base_language' => 'required|string|max:10',
            'country_code' => 'nullable|string|max:2',
        ]);

        try
        {
            // Create the language variant
            $data = $request->all();
            $data['team_id'] = auth()->user()->currentTeam->id;
            $languageVariant = LanguageVariant::create($data);

            // Load the base language relationship
            $languageVariant->load('baseLanguage');

            return response()->json([
                'success' => true,
                'message' => 'Language variant created successfully',
                'data' => $languageVariant,
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ], 201);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error creating language variant: '.$e->getMessage(),
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(LanguageVariant $languageVariant)
    {
        // Authorize the request
        $this->authorize('view', $languageVariant);

        // Load the base language relationship
        $languageVariant->load('baseLanguage');

        return response()->json([
            'success' => true,
            'data' => $languageVariant,
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LanguageVariant $languageVariant)
    {
        // Authorize the request
        $this->authorize('update', $languageVariant);

        // Validate the request
        $request->validate([
            'code' => 'sometimes|required|string|max:10|unique:language_variants,code,'.$languageVariant->id.',id,team_id,'.auth()->user()->currentTeam->id,
            'name' => 'sometimes|required|string|max:255',
            'base_language' => 'sometimes|required|string|max:10',
            'country_code' => 'nullable|string|max:2',
        ]);

        try
        {
            // Update the language variant
            $languageVariant->update($request->all());

            // Load the base language relationship
            $languageVariant->load('baseLanguage');

            return response()->json([
                'success' => true,
                'message' => 'Language variant updated successfully',
                'data' => $languageVariant,
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error updating language variant: '.$e->getMessage(),
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LanguageVariant $languageVariant)
    {
        // Authorize the request
        $this->authorize('delete', $languageVariant);

        try
        {
            // Delete the language variant
            $languageVariant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Language variant deleted successfully',
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting language variant: '.$e->getMessage(),
                'user' => [
                    'id' => auth()->id(),
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ], 500);
        }
    }

    /**
     * Get variants for a specific base language.
     */
    public function getVariantsFor($baseLanguage)
    {
        // Authorize the request
        $this->authorize('viewAny', LanguageVariant::class);

        // Get variants for the base language
        $variants = LanguageVariant::getVariantsFor($baseLanguage);

        return response()->json([
            'success' => true,
            'data' => $variants,
            'count' => $variants->count(),
            'base_language' => $baseLanguage,
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }
}
