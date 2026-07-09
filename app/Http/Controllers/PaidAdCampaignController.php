<?php

namespace App\Http\Controllers;

use App\DataTables\PaidAdCampaignDataTable;
use App\Enums\AdConnectionStatus;
use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Http\Requests\StorePaidAdCampaignRequest;
use App\Http\Requests\UpdatePaidAdCampaignRequest;
use App\Jobs\PublishPaidAdCampaignJob;
use App\Jobs\SyncPaidAdMetricsJob;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdAudience;
use App\Models\PaidAdCampaign;
use App\Services\PaidAdMetricsAggregator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PaidAdCampaignController extends Controller
{
    public function index(PaidAdCampaignDataTable $dataTable): mixed
    {
        $this->authorize('viewAny', PaidAdCampaign::class);
        $this->ensureModule();

        return $dataTable->render('paid-ads.index');
    }

    public function create(): View
    {
        $this->authorize('create', PaidAdCampaign::class);
        $this->ensureModule();

        $campaign = new PaidAdCampaign;
        $campaign->status = PaidAdCampaignStatus::Draft;
        $campaign->budget_type = 'daily';
        $campaign->currency = 'EUR';

        return view('paid-ads.form', $this->formDependencies($campaign));
    }

    public function store(StorePaidAdCampaignRequest $request): RedirectResponse
    {
        $this->ensureModule();

        $validated = $request->validated();
        $platformConnectionIds = $validated['platforms'] ?? [];
        $audienceIds = $validated['audiences'] ?? [];
        unset($validated['platforms'], $validated['audiences']);

        $validated['status'] = PaidAdCampaignStatus::Draft->value;

        $campaign = PaidAdCampaign::create($validated);

        $this->syncPlatforms($campaign, $platformConnectionIds);
        $campaign->audiences()->sync($audienceIds);

        return redirect()
            ->route('paid-ads.show', $campaign->id)
            ->with('success', __('Campaign created successfully.'));
    }

    public function show(string $id): View
    {
        $this->ensureModule();

        $campaign = PaidAdCampaign::query()
            ->with(['platforms.connection', 'audiences', 'creator:id,name'])
            ->findOrFail($id);

        $this->authorize('view', $campaign);

        $metrics = app(PaidAdMetricsAggregator::class)->forCampaign($campaign);

        return view('paid-ads.show', compact('campaign', 'metrics'));
    }

    public function edit(string $id): View
    {
        $this->ensureModule();

        $campaign = PaidAdCampaign::query()->with(['platforms', 'audiences'])->findOrFail($id);
        $this->authorize('update', $campaign);

        return view('paid-ads.form', $this->formDependencies($campaign));
    }

    public function update(UpdatePaidAdCampaignRequest $request, string $id): RedirectResponse
    {
        $this->ensureModule();

        $campaign = PaidAdCampaign::query()->findOrFail($id);
        $this->authorize('update', $campaign);

        $validated = $request->validated();
        $platformConnectionIds = $validated['platforms'] ?? [];
        $audienceIds = $validated['audiences'] ?? [];
        unset($validated['platforms'], $validated['audiences']);

        $campaign->update($validated);

        $this->syncPlatforms($campaign, $platformConnectionIds);
        $campaign->audiences()->sync($audienceIds);

        return redirect()
            ->route('paid-ads.show', $campaign->id)
            ->with('success', __('Campaign updated successfully.'));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->ensureModule();

        $campaign = PaidAdCampaign::query()->findOrFail($id);
        $this->authorize('delete', $campaign);
        $campaign->delete();

        return response()->json(['success' => __('The record has been deleted.')], 200);
    }

    public function publish(string $id): RedirectResponse
    {
        $this->ensureModule();

        $campaign = PaidAdCampaign::query()->with('platforms')->findOrFail($id);
        $this->authorize('publish', $campaign);

        if ($campaign->platforms->isEmpty())
        {
            return back()->with('warning', __('Add at least one connected platform before publishing.'));
        }

        $campaign->forceFill(['status' => PaidAdCampaignStatus::Publishing])->save();
        PublishPaidAdCampaignJob::dispatch($campaign->id);

        return back()->with('success', __('Publishing started. Platform statuses will update shortly.'));
    }

    public function pause(string $id): RedirectResponse
    {
        $this->ensureModule();

        $campaign = PaidAdCampaign::query()->findOrFail($id);
        $this->authorize('publish', $campaign);

        $campaign->forceFill(['status' => PaidAdCampaignStatus::Paused])->save();

        return back()->with('success', __('Campaign paused.'));
    }

    public function resume(string $id): RedirectResponse
    {
        $this->ensureModule();

        $campaign = PaidAdCampaign::query()->findOrFail($id);
        $this->authorize('publish', $campaign);

        $campaign->forceFill(['status' => PaidAdCampaignStatus::Active])->save();

        return back()->with('success', __('Campaign resumed.'));
    }

    public function syncMetrics(string $id): RedirectResponse
    {
        $this->ensureModule();

        $campaign = PaidAdCampaign::query()->with('platforms')->findOrFail($id);
        $this->authorize('view', $campaign);

        foreach ($campaign->platforms as $campaignPlatform)
        {
            SyncPaidAdMetricsJob::dispatch($campaignPlatform->id);
        }

        return back()->with('success', __('Metric sync queued.'));
    }

    /**
     * @param  array<int, int>  $connectionIds
     */
    private function syncPlatforms(PaidAdCampaign $campaign, array $connectionIds): void
    {
        $connections = AdPlatformConnection::query()
            ->whereIn('id', $connectionIds)
            ->get()
            ->keyBy('id');

        $keepPlatforms = [];

        foreach ($connectionIds as $connectionId)
        {
            $connection = $connections->get($connectionId);
            if ($connection === null)
            {
                continue;
            }

            $campaign->platforms()->updateOrCreate(
                ['platform' => $connection->platform->value],
                ['ad_platform_connection_id' => $connection->id],
            );

            $keepPlatforms[] = $connection->platform->value;
        }

        $campaign->platforms()
            ->whereNotIn('platform', $keepPlatforms)
            ->whereIn('publish_status', ['pending', 'failed'])
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function formDependencies(PaidAdCampaign $campaign): array
    {
        $connections = AdPlatformConnection::query()
            ->where('status', AdConnectionStatus::Active->value)
            ->orderBy('platform')
            ->get();

        $audiences = PaidAdAudience::query()->orderBy('name')->get();

        $selectedConnectionIds = $campaign->exists
            ? $campaign->platforms->pluck('ad_platform_connection_id')->filter()->all()
            : [];

        $selectedAudienceIds = $campaign->exists
            ? $campaign->audiences->pluck('id')->all()
            : [];

        return [
            'data' => $campaign,
            'objectives' => PaidAdObjective::cases(),
            'connections' => $connections,
            'audiences' => $audiences,
            'selectedConnectionIds' => $selectedConnectionIds,
            'selectedAudienceIds' => $selectedAudienceIds,
        ];
    }

    private function ensureModule(): void
    {
        if (! auth()->user()?->currentTeam?->hasModule('paid_ads'))
        {
            abort(404);
        }
    }
}
