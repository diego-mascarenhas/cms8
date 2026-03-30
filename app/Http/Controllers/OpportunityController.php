<?php

namespace App\Http\Controllers;

use App\DataTables\OpportunityDataTable;
use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityRequest;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    public function index(OpportunityDataTable $dataTable, Request $request)
    {
        $this->authorize('viewAny', Opportunity::class);

        if (! auth()->user()->currentTeam?->hasModule('opportunities'))
        {
            abort(404);
        }

        return $dataTable->render('opportunity.index');
    }

    public function create(Request $request)
    {
        $this->authorize('create', Opportunity::class);

        if (! auth()->user()->currentTeam?->hasModule('opportunities'))
        {
            abort(404);
        }

        $data = new Opportunity;
        $data->opened_at = now()->toDateString();
        $data->responsible_id = auth()->id();
        $data->opportunity_stage_id = OpportunityStage::query()->orderBy('sort_order')->value('id');

        if ($request->filled('contact_id'))
        {
            $data->contact_id = (int) $request->contact_id;
        }

        return view('opportunity.form', $this->formDependencies($data));
    }

    public function store(StoreOpportunityRequest $request)
    {
        if (! auth()->user()->currentTeam?->hasModule('opportunities'))
        {
            abort(404);
        }

        $validated = $request->validated();
        $validated['team_id'] = auth()->user()->currentTeam->id;

        if (empty($validated['responsible_id']))
        {
            $validated['responsible_id'] = auth()->id();
        }

        $validated = $this->mergeOfferingIntoValidated($validated, $request);

        $opportunity = Opportunity::create($validated);

        return redirect()
            ->route('opportunity.show', $opportunity->id)
            ->with('success', __('Opportunity created successfully.'));
    }

    public function show(string $id)
    {
        if (! auth()->user()->currentTeam?->hasModule('opportunities'))
        {
            abort(404);
        }

        $opportunity = Opportunity::query()
            ->with([
                'contact',
                'responsible:id,name',
                'stage',
                'currency',
                'offering',
            ])
            ->findOrFail($id);

        $this->authorize('view', $opportunity);

        $interactions = $opportunity->interactions()
            ->with(['user:id,name'])
            ->orderByDesc('occurred_at')
            ->get();

        return view('opportunity.show', compact('opportunity', 'interactions'));
    }

    public function edit(string $id)
    {
        if (! auth()->user()->currentTeam?->hasModule('opportunities'))
        {
            abort(404);
        }

        $data = Opportunity::query()->findOrFail($id);
        $this->authorize('update', $data);

        return view('opportunity.form', $this->formDependencies($data));
    }

    public function update(UpdateOpportunityRequest $request, string $id)
    {
        if (! auth()->user()->currentTeam?->hasModule('opportunities'))
        {
            abort(404);
        }

        $opportunity = Opportunity::query()->findOrFail($id);
        $this->authorize('update', $opportunity);

        $validated = $request->validated();
        $validated = $this->mergeOfferingIntoValidated($validated, $request);

        $opportunity->update($validated);

        return redirect()
            ->route('opportunity.show', $opportunity->id)
            ->with('success', __('Opportunity updated successfully.'));
    }

    public function destroy(string $id)
    {
        if (! auth()->user()->currentTeam?->hasModule('opportunities'))
        {
            abort(404);
        }

        $model = Opportunity::query()->findOrFail($id);
        $this->authorize('delete', $model);
        $model->delete();

        return response()->json(['success' => __('The record has been deleted.')], 200);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mergeOfferingIntoValidated(array $validated, Request $request): array
    {
        $kind = $validated['offering_kind'] ?? 'none';
        unset($validated['offering_kind'], $validated['product_id'], $validated['service_id']);

        if ($kind === 'product')
        {
            $validated['offering_type'] = \App\Models\Product::class;
            $validated['offering_id'] = $request->integer('product_id');
        } elseif ($kind === 'service')
        {
            $validated['offering_type'] = 'service';
            $validated['offering_id'] = $request->integer('service_id');
        } else
        {
            $validated['offering_type'] = null;
            $validated['offering_id'] = null;
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function formDependencies(Opportunity $data): array
    {
        $teamId = auth()->user()->currentTeam->id;

        $contacts = Contact::query()
            ->orderBy('name')
            ->orderBy('surname')
            ->get();

        $stages = OpportunityStage::query()->orderBy('sort_order')->get();

        $users = User::query()
            ->whereHas('teams', fn ($q) => $q->where('teams.id', auth()->user()->current_team_id))
            ->orderBy('name')
            ->get();

        $currencies = Currency::query()->where('status', true)->orderBy('code')->get();

        $products = Product::query()->orderBy('name')->get();

        $services = Service::withoutGlobalScopes()
            ->whereHas('client', fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('id')
            ->limit(500)
            ->get();

        $offeringKind = 'none';
        $productId = null;
        $serviceId = null;

        if ($data->offering_type === \App\Models\Product::class && $data->offering_id)
        {
            $offeringKind = 'product';
            $productId = $data->offering_id;
        } elseif ($data->offering_type === \App\Models\Service::class && $data->offering_id)
        {
            $offeringKind = 'service';
            $serviceId = $data->offering_id;
        }

        return compact(
            'data',
            'contacts',
            'stages',
            'users',
            'currencies',
            'products',
            'services',
            'offeringKind',
            'productId',
            'serviceId',
        );
    }
}
