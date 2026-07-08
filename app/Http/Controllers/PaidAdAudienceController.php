<?php

namespace App\Http\Controllers;

use App\Models\PaidAdAudience;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaidAdAudienceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PaidAdAudience::class);
        $this->ensureModule();

        $audiences = PaidAdAudience::query()
            ->withCount('campaigns')
            ->orderBy('name')
            ->paginate(20);

        return view('paid-ads.audiences.index', compact('audiences'));
    }

    public function create(): View
    {
        $this->authorize('create', PaidAdAudience::class);
        $this->ensureModule();

        $audience = new PaidAdAudience;

        return view('paid-ads.audiences.form', ['data' => $audience]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PaidAdAudience::class);
        $this->ensureModule();

        $validated = $this->validated($request);

        PaidAdAudience::create($validated);

        return redirect()
            ->route('paid-ads.audiences.index')
            ->with('success', __('Audience created successfully.'));
    }

    public function edit(string $id): View
    {
        $this->ensureModule();

        $audience = PaidAdAudience::query()->findOrFail($id);
        $this->authorize('update', $audience);

        return view('paid-ads.audiences.form', ['data' => $audience]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureModule();

        $audience = PaidAdAudience::query()->findOrFail($id);
        $this->authorize('update', $audience);

        $audience->update($this->validated($request));

        return redirect()
            ->route('paid-ads.audiences.index')
            ->with('success', __('Audience updated successfully.'));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->ensureModule();

        $audience = PaidAdAudience::query()->findOrFail($id);
        $this->authorize('delete', $audience);
        $audience->delete();

        return response()->json(['success' => __('The record has been deleted.')], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['custom', 'lookalike', 'retargeting', 'saved'])],
            'targeting_rules' => ['nullable', 'array'],
            'targeting_rules.locations' => ['nullable', 'string', 'max:1000'],
            'targeting_rules.interests' => ['nullable', 'string', 'max:1000'],
            'targeting_rules.age_min' => ['nullable', 'integer', 'min:13', 'max:99'],
            'targeting_rules.age_max' => ['nullable', 'integer', 'min:13', 'max:99'],
            'estimated_size' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function ensureModule(): void
    {
        if (! auth()->user()?->currentTeam?->hasModule('paid_ads'))
        {
            abort(404);
        }
    }
}
