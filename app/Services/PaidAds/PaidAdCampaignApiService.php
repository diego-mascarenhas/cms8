<?php

namespace App\Services\PaidAds;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdAudience;
use App\Models\PaidAdCampaign;
use App\Models\Team;
use App\Services\PaidAdMetricsAggregator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PaidAdCampaignApiService
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly PaidAdMetricsAggregator $metrics,
        private readonly PaidAdCreativeAssetService $assets,
    ) {}

    public function paginate(Team $team, string $search = '', int $page = 1, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = PaidAdCampaign::query()
            ->where('team_id', $team->id)
            ->with(['platforms.connection', 'audiences:id,name,type', 'creator:id,name'])
            ->orderByDesc('updated_at');

        if ($search !== '')
        {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{scheduled: \Illuminate\Support\Collection<int, PaidAdCampaign>, unscheduled: \Illuminate\Support\Collection<int, PaidAdCampaign>}
     */
    public function calendar(Team $team, string $from, string $to): array
    {
        $rangeStart = Carbon::parse($from)->startOfDay();
        $rangeEnd = Carbon::parse($to)->endOfDay();

        $with = ['platforms.connection', 'audiences:id,name,type', 'creator:id,name'];

        $scheduled = PaidAdCampaign::query()
            ->where('team_id', $team->id)
            ->with($with)
            ->whereNotNull('start_at')
            ->where('start_at', '<=', $rangeEnd)
            ->where(function ($query) use ($rangeStart)
            {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $rangeStart);
            })
            ->orderBy('start_at')
            ->get();

        $unscheduled = PaidAdCampaign::query()
            ->where('team_id', $team->id)
            ->with($with)
            ->whereNull('start_at')
            ->where('status', '!=', PaidAdCampaignStatus::Archived->value)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return [
            'scheduled' => $scheduled,
            'unscheduled' => $unscheduled,
        ];
    }

    public function findForTeam(Team $team, int $id): ?PaidAdCampaign
    {
        return PaidAdCampaign::query()
            ->where('team_id', $team->id)
            ->with(['platforms.connection', 'audiences', 'creator:id,name'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, int>  $platformConnectionIds
     * @param  array<int, int>  $audienceIds
     */
    public function create(Team $team, int $userId, array $validated, array $platformConnectionIds, array $audienceIds): PaidAdCampaign
    {
        $validated['team_id'] = $team->id;
        $validated['created_by'] = $userId;
        $validated['status'] = PaidAdCampaignStatus::Draft->value;

        $campaign = PaidAdCampaign::query()->create($validated);
        $this->syncPlatforms($campaign, $platformConnectionIds);
        $campaign->audiences()->sync($audienceIds);
        $campaign = $this->findForTeam($team, $campaign->id) ?? $campaign;
        app(PaidAdCampaignCalendarSyncer::class)->sync($campaign);

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, int>  $platformConnectionIds
     * @param  array<int, int>  $audienceIds
     */
    public function update(PaidAdCampaign $campaign, Team $team, array $validated, array $platformConnectionIds, array $audienceIds): PaidAdCampaign
    {
        $campaign->update($validated);
        $this->syncPlatforms($campaign, $platformConnectionIds);
        $campaign->audiences()->sync($audienceIds);
        $campaign = $this->findForTeam($team, $campaign->id) ?? $campaign->fresh();
        app(PaidAdCampaignCalendarSyncer::class)->sync($campaign);

        return $campaign;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForList(PaidAdCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'objective' => [
                'key' => $campaign->objective instanceof PaidAdObjective
                    ? $campaign->objective->value
                    : (string) $campaign->objective,
                'label' => $campaign->objective instanceof PaidAdObjective
                    ? $campaign->objective->label()
                    : (string) $campaign->objective,
            ],
            'status' => [
                'key' => $campaign->status instanceof PaidAdCampaignStatus
                    ? $campaign->status->value
                    : (string) $campaign->status,
                'label' => $campaign->status instanceof PaidAdCampaignStatus
                    ? $campaign->status->label()
                    : (string) $campaign->status,
            ],
            'budget_type' => $campaign->budget_type,
            'budget_amount' => $campaign->budget_amount !== null ? (float) $campaign->budget_amount : null,
            'currency' => $campaign->currency,
            'start_at' => optional($campaign->start_at)?->toIso8601String(),
            'end_at' => optional($campaign->end_at)?->toIso8601String(),
            'platforms' => $campaign->platforms->map(fn ($platform) => $this->formatPlatform($platform))->values()->all(),
            'audiences_label' => $campaign->audiences->pluck('name')->filter()->implode(', ') ?: '—',
            'updated_at' => optional($campaign->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForShow(PaidAdCampaign $campaign): array
    {
        $list = $this->formatForList($campaign);

        return array_merge($list, [
            'targeting' => $campaign->targeting ?? [],
            'creative' => $this->assets->formatCreative($campaign->creative ?? []),
            'settings' => $campaign->settings ?? [],
            'audiences' => $campaign->audiences->map(fn (PaidAdAudience $audience) => [
                'id' => $audience->id,
                'name' => $audience->name,
                'type' => $audience->type,
            ])->values()->all(),
            'platform_connection_ids' => $campaign->platforms
                ->pluck('ad_platform_connection_id')
                ->filter()
                ->values()
                ->all(),
            'audience_ids' => $campaign->audiences->pluck('id')->values()->all(),
            'creator' => $campaign->creator
                ? ['id' => $campaign->creator->id, 'name' => $campaign->creator->name]
                : null,
            'metrics' => $this->metrics->forCampaign($campaign),
            'created_at' => optional($campaign->created_at)?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function lookups(Team $team): array
    {
        $connections = AdPlatformConnection::query()
            ->where('team_id', $team->id)
            ->where('status', AdConnectionStatus::Active->value)
            ->orderBy('platform')
            ->get()
            ->map(fn (AdPlatformConnection $connection) => [
                'id' => $connection->id,
                'platform' => $connection->platform->value,
                'platform_label' => $connection->platform->label(),
                'ad_account_name' => $connection->ad_account_name,
                'status' => $connection->status->value,
            ])
            ->values()
            ->all();

        $audiences = PaidAdAudience::query()
            ->where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (PaidAdAudience $audience) => [
                'id' => $audience->id,
                'name' => $audience->name,
                'type' => $audience->type,
            ])
            ->values()
            ->all();

        $connectionsByPlatform = collect($connections)->keyBy('platform');

        return [
            'objectives' => collect(PaidAdObjective::cases())->map(fn (PaidAdObjective $objective) => [
                'key' => $objective->value,
                'label' => $objective->label(),
            ])->values()->all(),
            'budget_types' => [
                ['key' => 'daily', 'label' => __('Daily')],
                ['key' => 'lifetime', 'label' => __('Lifetime')],
            ],
            'currencies' => ['EUR', 'USD', 'GBP'],
            'connections' => $connections,
            'platforms' => collect(AdPlatform::cases())
                ->filter(fn (AdPlatform $platform) => $platform->isEnabled())
                ->map(function (AdPlatform $platform) use ($connectionsByPlatform)
                {
                    $connection = $connectionsByPlatform->get($platform->value);

                    return [
                        'key' => $platform->value,
                        'label' => $platform->label(),
                        'color' => $platform->color(),
                        'connected' => $connection !== null,
                        'connection_id' => $connection['id'] ?? null,
                    ];
                })
                ->values()
                ->all(),
            'audiences' => $audiences,
            'formats' => $this->assets->formats(),
        ];
    }

    /**
     * @param  array<int, int>  $connectionIds
     */
    public function syncPlatforms(PaidAdCampaign $campaign, array $connectionIds): void
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
    private function formatPlatform($platform): array
    {
        return [
            'id' => $platform->id,
            'platform' => $platform->platform instanceof \App\Enums\AdPlatform
                ? $platform->platform->value
                : (string) $platform->platform,
            'platform_label' => $platform->platform instanceof \App\Enums\AdPlatform
                ? $platform->platform->label()
                : (string) $platform->platform,
            'publish_status' => $platform->publish_status instanceof \App\Enums\AdPublishStatus
                ? $platform->publish_status->value
                : (string) $platform->publish_status,
            'publish_error' => $platform->publish_error,
            'external_campaign_id' => $platform->external_campaign_id,
            'ad_platform_connection_id' => $platform->ad_platform_connection_id,
            'connection' => $platform->connection
                ? [
                    'id' => $platform->connection->id,
                    'ad_account_name' => $platform->connection->ad_account_name,
                    'status' => $platform->connection->status instanceof \App\Enums\AdConnectionStatus
                        ? $platform->connection->status->value
                        : (string) $platform->connection->status,
                ]
                : null,
            'last_synced_at' => optional($platform->last_synced_at)?->toIso8601String(),
        ];
    }
}
