<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ApolloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApolloController extends Controller
{
    /**
     * Show Apollo search page (people and organizations).
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('create', Contact::class);

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $team = auth()->user()->currentTeam;
        $team->resetProspectMonthlyLimitsIfNeeded();
        $remainingProspectCredits = $team->getRemainingProspectCredits();
        $canImportProspects = $team->canImportProspects(1);

        return view('contact.apollo', [
            'remainingProspectCredits' => $remainingProspectCredits,
            'canImportProspects' => $canImportProspects,
        ]);
    }

    /**
     * Search people via Apollo API (AJAX).
     */
    public function searchPeople(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

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
            'organization_num_employees_ranges' => 'nullable|array',
            'organization_num_employees_ranges.*' => 'string|max:50',
            'q_keywords' => 'nullable|string|max:500',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'person_titles' => $validated['person_titles'] ?? null,
            'person_locations' => $validated['person_locations'] ?? null,
            'person_seniorities' => $validated['person_seniorities'] ?? null,
            'organization_locations' => $validated['organization_locations'] ?? null,
            'q_organization_domains_list' => $validated['q_organization_domains_list'] ?? null,
            'organization_num_employees_ranges' => $validated['organization_num_employees_ranges'] ?? null,
            'q_keywords' => $validated['q_keywords'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);

        try
        {
            $service = new ApolloService;
            $result = $service->searchPeople($filters, $page, $perPage);

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
     * Search organizations via Apollo API (AJAX).
     */
    public function searchOrganizations(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $validated = $request->validate([
            'q_organization_domains' => 'nullable|string|max:1000',
            'organization_locations' => 'nullable|array',
            'organization_locations.*' => 'string|max:255',
            'organization_num_employees_ranges' => 'nullable|array',
            'organization_num_employees_ranges.*' => 'string|max:50',
            'q_keywords' => 'nullable|string|max:500',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'q_organization_domains' => $validated['q_organization_domains'] ?? null,
            'organization_locations' => $validated['organization_locations'] ?? null,
            'organization_num_employees_ranges' => $validated['organization_num_employees_ranges'] ?? null,
            'q_keywords' => $validated['q_keywords'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);

        try
        {
            $service = new ApolloService;
            $result = $service->searchOrganizations($filters, $page, $perPage);

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
     * Add an Apollo person as a new contact.
     */
    public function addPersonAsContact(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Contact::class);

        if (! auth()->user()->currentTeam)
        {
            if ($request->wantsJson())
            {
                return response()->json(['message' => 'No team selected.'], 422);
            }

            return redirect()->route('error-without-team');
        }

        $validated = $request->validate([
            'apollo_id' => 'required|string|max:100',
            'first_name' => 'required|string|max:255',
            'last_name_obfuscated' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:500',
            'organization_name' => 'nullable|string|max:500',
            'person_data' => 'nullable|string|max:65535',
        ]);

        $team = auth()->user()->currentTeam;

        $apolloData = [
            'apollo_id' => $validated['apollo_id'],
            'title' => $validated['title'] ?? null,
            'organization_name' => $validated['organization_name'] ?? null,
        ];
        if (! empty($validated['person_data']))
        {
            $decoded = json_decode($validated['person_data'], true);
            if (is_array($decoded))
            {
                $apolloData = $decoded;
            }
        }

        $position = $this->normalizeProspectPosition($apolloData);
        $cost = $this->getProspectCreditsCost($position);

        $team->resetProspectMonthlyLimitsIfNeeded();
        if (! $team->canImportProspects($cost))
        {
            if ($request->wantsJson())
            {
                return response()->json([
                    'message' => __('No tienes suficientes créditos de prospectos. Contrata un plan o compra más créditos en Suscripciones.'),
                ], 402);
            }

            return redirect()
                ->route('subscription.index')
                ->with('error', __('No tienes suficientes créditos de prospectos. Contrata un plan o compra más créditos.'));
        }

        if (! $team->decrementProspectCredits($cost))
        {
            if ($request->wantsJson())
            {
                return response()->json([
                    'message' => __('No tienes suficientes créditos de prospectos.'),
                ], 402);
            }

            return redirect()->route('subscription.index')->with('error', __('No tienes suficientes créditos de prospectos.'));
        }

        $enriched = null;
        try
        {
            $service = new ApolloService;
            $personForEnrich = [
                'id' => $validated['apollo_id'],
                'first_name' => $validated['first_name'],
                'organization_name' => $apolloData['organization_name'] ?? null,
                'organization' => $apolloData['organization'] ?? null,
            ];
            $enriched = $service->enrichPerson($personForEnrich);
        } catch (\Throwable $e)
        {
            $enriched = null;
        }

        if (is_array($enriched) && ! empty($enriched))
        {
            $apolloData = array_merge($enriched, ['apollo_id' => $enriched['id'] ?? $validated['apollo_id']]);
            $name = trim(($enriched['first_name'] ?? '').' '.($enriched['last_name'] ?? ''));
            if ($name === '')
            {
                $name = $enriched['name'] ?? trim($validated['first_name'].' '.($validated['last_name_obfuscated'] ?? '')) ?: 'Contact';
            }
        } else
        {
            $lastName = $validated['last_name'] ?? $validated['last_name_obfuscated'] ?? '';
            $name = trim($validated['first_name'].' '.$lastName) ?: 'Contact';
        }

        $contactData = [
            'team_id' => $team->id,
            'creator_id' => auth()->id(),
            'responsible_id' => auth()->id(),
            'name' => $name ?: 'Contact',
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
            'data' => ['apollo' => $apolloData],
        ];
        if (! empty($apolloData['email']))
        {
            $contactData['email'] = $apolloData['email'];
        }
        $phone = $apolloData['phone'] ?? null;
        if (! empty($phone))
        {
            $contactData['phone'] = is_string($phone) ? $phone : ($phone['number'] ?? null);
        }

        $contact = Contact::create($contactData);

        if ($request->wantsJson())
        {
            return response()->json([
                'message' => __('Contact created.'),
                'contact_id' => $contact->id,
                'redirect_url' => route('contact.show', $contact->id),
            ], 201);
        }

        return redirect()
            ->route('contact.show', $contact->id)
            ->with('success', __('Contacto creado desde la búsqueda de prospectos.'));
    }

    /**
     * Normalize prospect position (seniority) from API data to a config key.
     */
    private function normalizeProspectPosition(array $apolloData): string
    {
        $raw = $apolloData['apollo_raw'] ?? $apolloData;
        $seniority = $raw['seniority'] ?? $raw['person_seniority'] ?? null;
        if (! is_string($seniority) || $seniority === '')
        {
            return 'manager';
        }

        $key = strtolower(trim(preg_replace('/[^a-z0-9_]/', '_', $seniority)));
        $key = str_replace('__', '_', $key);
        $allowed = array_keys(config('prospects.credits_per_position', []));

        return in_array($key, $allowed, true) ? $key : 'manager';
    }

    /**
     * Get credit cost for a prospect position.
     */
    private function getProspectCreditsCost(string $position): int
    {
        $credits = config('prospects.credits_per_position', []);

        return (int) ($credits[$position] ?? config('prospects.default_credits', 1));
    }
}
