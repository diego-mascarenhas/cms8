<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CertificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * Display a listing of the certifications.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Certification::class);

        $query = Certification::with(['team', 'languageRelation']);

        // Filter by language if provided
        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        // Search by certification name if provided
        if ($request->has('search')) {
            $query->where('certification', 'like', '%' . $request->search . '%');
        }

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $certifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $certifications,
            'message' => 'Certifications retrieved successfully'
        ]);
    }

    /**
     * Store a newly created certification in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Certification::class);

        $validated = $request->validate([
            'certification' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:10'],
        ]);

        $validated['team_id'] = auth()->user()->currentTeam->id;

        $certification = Certification::create($validated);
        $certification->load(['team', 'languageRelation']);

        return response()->json([
            'success' => true,
            'data' => $certification,
            'message' => 'Certification created successfully'
        ], 201);
    }

    /**
     * Display the specified certification.
     */
    public function show(Certification $certification): JsonResponse
    {
        $this->authorize('view', $certification);

        $certification->load(['team', 'languageRelation']);

        return response()->json([
            'success' => true,
            'data' => $certification,
            'message' => 'Certification retrieved successfully'
        ]);
    }

    /**
     * Update the specified certification in storage.
     */
    public function update(Request $request, Certification $certification): JsonResponse
    {
        $this->authorize('update', $certification);

        $validated = $request->validate([
            'certification' => ['sometimes', 'string', 'max:255'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        $certification->update($validated);
        $certification->load(['team', 'languageRelation']);

        return response()->json([
            'success' => true,
            'data' => $certification,
            'message' => 'Certification updated successfully'
        ]);
    }

    /**
     * Remove the specified certification from storage.
     */
    public function destroy(Certification $certification): JsonResponse
    {
        $this->authorize('delete', $certification);

        $certification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Certification deleted successfully'
        ]);
    }

    /**
     * Get all languages with certifications count.
     */
    public function languages(): JsonResponse
    {
        $this->authorize('viewAny', Certification::class);

        $languages = Language::withCount('certifications')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $languages,
            'message' => 'Languages with certifications retrieved successfully'
        ]);
    }

    /**
     * Get certifications by language.
     */
    public function byLanguage(string $languageCode): JsonResponse
    {
        $this->authorize('viewAny', Certification::class);

        $certifications = Certification::with(['team', 'languageRelation'])
            ->where('language', $languageCode)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $certifications,
            'message' => 'Certifications filtered by language retrieved successfully'
        ]);
    }

    /**
     * Get certification types grouped by categories.
     */
    public function types(): JsonResponse
    {
        $this->authorize('viewAny', Certification::class);

        $certifications = Certification::select('certification')
            ->distinct()
            ->get();

        $types = [
            'translation' => [],
            'language_proficiency' => [],
            'audiovisual' => [],
            'professional' => [],
            'other' => []
        ];

        foreach ($certifications as $cert) {
            $name = strtolower($cert->certification);
            
            if (str_contains($name, 'ata') || str_contains($name, 'iso') || str_contains($name, 'proz') || str_contains($name, 'trados')) {
                $types['translation'][] = $cert->certification;
            } elseif (str_contains($name, 'dele') || str_contains($name, 'toefl') || str_contains($name, 'ielts') || 
                      str_contains($name, 'cambridge') || str_contains($name, 'delf') || str_contains($name, 'dalf') || 
                      str_contains($name, 'goethe') || str_contains($name, 'jlpt') || str_contains($name, 'hsk') || 
                      str_contains($name, 'topik') || str_contains($name, 'tcf') || str_contains($name, 'testdaf') || 
                      str_contains($name, 'siele') || str_contains($name, 'toeic')) {
                $types['language_proficiency'][] = $cert->certification;
            } elseif (str_contains($name, 'atrae') || str_contains($name, 'subtitling') || str_contains($name, 'eztitles')) {
                $types['audiovisual'][] = $cert->certification;
            } elseif (str_contains($name, 'ciol') || str_contains($name, 'traductor público') || str_contains($name, 'certified pro')) {
                $types['professional'][] = $cert->certification;
            } else {
                $types['other'][] = $cert->certification;
            }
        }

        // Remove empty categories and sort
        $types = array_filter($types, function($category) {
            return !empty($category);
        });

        foreach ($types as $key => $category) {
            sort($types[$key]);
        }

        return response()->json([
            'success' => true,
            'data' => $types,
            'message' => 'Certification types retrieved successfully'
        ]);
    }
}
