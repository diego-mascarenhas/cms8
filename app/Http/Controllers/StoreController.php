<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequest;
use App\Models\Store;

class StoreController extends Controller
{
    public function index()
    {
        $stores = Store::query()
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        return view('store.index', compact('stores'));
    }

    public function create()
    {
        $store = new Store;

        return view('store.form', compact('store'));
    }

    public function show(string $id)
    {
        $store = Store::query()->findOrFail($id);

        return view('store.show', compact('store'));
    }

    public function store(StoreRequest $request)
    {
        $teamId = auth()->user()->currentTeam?->id;

        if (! $teamId)
        {
            return redirect()->route('store.index')->with('error', __('No active team found.'));
        }

        $payload = $this->payloadWithCheckoutData($request->validated(), null);
        $payload['team_id'] = $teamId;
        $payload['is_main'] = $request->boolean('is_main');
        $payload['status'] = $request->boolean('status');

        if ($payload['is_main'])
        {
            Store::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->update(['is_main' => false]);
        }

        Store::withoutGlobalScope('team')->create($payload);

        return redirect()->route('store.index')->with('success', __('Store created successfully.'));
    }

    public function edit(string $id)
    {
        $store = Store::query()->findOrFail($id);

        return view('store.form', compact('store'));
    }

    public function update(StoreRequest $request, string $id)
    {
        $store = Store::query()->findOrFail($id);
        $teamId = auth()->user()->currentTeam?->id;

        $payload = $this->payloadWithCheckoutData($request->validated(), $store);
        $payload['is_main'] = $request->boolean('is_main');
        $payload['status'] = $request->boolean('status');

        if ($payload['is_main'])
        {
            Store::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('id', '!=', $store->id)
                ->update(['is_main' => false]);
        }

        $store->update($payload);

        return redirect()->route('store.show', $store->id)->with('success', __('Store updated successfully.'));
    }

    public function destroy(string $id)
    {
        $store = Store::query()->findOrFail($id);

        if ($store->is_main)
        {
            return redirect()->route('store.index')->with('error', __('Main store cannot be deleted.'));
        }

        $store->delete();

        return redirect()->route('store.index')->with('success', __('Store deleted successfully.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payloadWithCheckoutData(array $validated, ?Store $existing): array
    {
        return Store::attributesFromValidated($validated, $existing);
    }
}
