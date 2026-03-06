<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Team;
use App\Services\ApolloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProspectflowController extends Controller
{
    /**
     * ProspectFlow landing (public).
     */
    public function index(): View
    {
        return view('prospectflow-demo');
    }

    /**
     * Search people via Apollo API – public, returns max 10 results.
     */
    public function searchPeople(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_titles' => 'nullable|array',
            'person_titles.*' => 'string|max:255',
            'person_locations' => 'nullable|array',
            'person_locations.*' => 'string|max:255',
            'person_seniorities' => 'nullable|array',
            'person_seniorities.*' => 'string|max:50',
            'organization_locations' => 'nullable|array',
            'organization_locations.*' => 'string|max:255',
            'q_organization_domains_list' => 'nullable|array',
            'q_organization_domains_list.*' => 'string|max:255',
            'q_keywords' => 'nullable|string|max:500',
        ]);

        $filters = array_filter([
            'person_titles' => $validated['person_titles'] ?? null,
            'person_locations' => $validated['person_locations'] ?? null,
            'person_seniorities' => $validated['person_seniorities'] ?? null,
            'organization_locations' => $validated['organization_locations'] ?? null,
            'q_organization_domains_list' => $validated['q_organization_domains_list'] ?? null,
            'q_keywords' => $validated['q_keywords'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        try
        {
            $service = new ApolloService;
            $result = $service->searchPeople($filters, 1, 10);

            $people = array_slice($result['people'], 0, 10);
            $result['people'] = $people;
            $result['total_entries'] = min($result['total_entries'], 10);
            $result['per_page'] = 10;

            return response()->json($result);
        } catch (\RuntimeException $e)
        {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502;

            return response()->json(
                ['message' => $e->getMessage()],
                $status,
            );
        }
    }

    /**
     * Store visitor email as Contact with status_id 3 (Convertido).
     */
    public function storeLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email:rfc|max:255',
        ]);

        $teamId = config('services.prospectflow.team_id');
        if (empty($teamId))
        {
            return response()->json([
                'message' => __('ProspectFlow is not configured.'),
                'success' => false,
            ], 503);
        }

        $team = Team::find($teamId);
        if (! $team)
        {
            return response()->json([
                'message' => __('Invalid configuration.'),
                'success' => false,
            ], 503);
        }

        Contact::withoutGlobalScopes()->firstOrCreate(
            [
                'email' => $validated['email'],
                'team_id' => $team->id,
            ],
            [
                'name' => Str::before($validated['email'], '@') ?: $validated['email'],
                'team_id' => $team->id,
                'status_id' => 3, // Convertido
                'creator_id' => $team->user_id,
            ],
        );

        return response()->json([
            'message' => __('Gracias, hemos recibido tu email.'),
            'success' => true,
        ], 201);
    }
}
