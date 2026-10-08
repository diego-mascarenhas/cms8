<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Module;
use App\Services\ApolloService;
use App\Services\Prospecting\InsufficientProspectCredits;
use App\Services\Prospecting\ProspectContactImporter;
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
        $this->ensureProspectingModuleEnabled();

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        $team = auth()->user()->currentTeam;
        $team->resetProspectMonthlyLimitsIfNeeded();
        $remainingProspectCredits = $team->getRemainingProspectCredits();
        $canImportProspects = $team->canImportProspects(1);

        $contactsModule = Module::where('key', 'contacts')->first();
        $contactCategories = collect();
        if ($contactsModule)
        {
            $contactCategories = Category::where('module_id', $contactsModule->id)
                ->where('status', 1)
                ->where(function ($q) use ($team)
                {
                    $q->whereNull('team_id')->orWhere('team_id', $team->id);
                })
                ->whereNull('parent_id')
                ->orderBy('order')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('contact.apollo', [
            'remainingProspectCredits' => $remainingProspectCredits,
            'canImportProspects' => $canImportProspects,
            'contactCategories' => $contactCategories,
        ]);
    }

    /**
     * Search people via Apollo API (AJAX).
     */
    public function searchPeople(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);
        $this->ensureProspectingModuleEnabled();

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
        $this->ensureProspectingModuleEnabled();

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
        $this->ensureProspectingModuleEnabled();

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
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $team = auth()->user()->currentTeam;

        $person = null;
        if (! empty($validated['person_data']))
        {
            $decoded = json_decode($validated['person_data'], true);
            if (is_array($decoded))
            {
                $person = $decoded;
            }
        }

        try
        {
            $contact = app(ProspectContactImporter::class)->import(
                $team,
                (int) auth()->id(),
                $validated['apollo_id'],
                $validated['first_name'],
                $validated['last_name'] ?? null,
                $validated['last_name_obfuscated'] ?? null,
                $validated['title'] ?? null,
                $validated['organization_name'] ?? null,
                $person,
                isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            );
        } catch (InsufficientProspectCredits $exception)
        {
            if ($request->wantsJson())
            {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 402);
            }

            return redirect()
                ->route('subscription.index')
                ->with('error', $exception->getMessage());
        }

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

    private function ensureProspectingModuleEnabled(): void
    {
        abort_unless(auth()->user()->currentTeam?->hasModule('prospecting'), 404);
    }
}
