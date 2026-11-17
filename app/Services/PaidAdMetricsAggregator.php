<?php

namespace App\Services;

use App\Models\PaidAdCampaign;
use App\Models\PaidAdMetricSnapshot;

class PaidAdMetricsAggregator
{
    /**
     * Aggregate totals for a single campaign across all its platforms.
     *
     * @return array{impressions: int, clicks: int, spend: float, conversions: int, ctr: float, cpc: float}
     */
    public function forCampaign(PaidAdCampaign $campaign): array
    {
        $row = PaidAdMetricSnapshot::query()
            ->whereIn('paid_ad_campaign_platform_id', $campaign->platforms()->select('id'))
            ->selectRaw('COALESCE(SUM(impressions),0) as impressions, COALESCE(SUM(clicks),0) as clicks, COALESCE(SUM(spend),0) as spend, COALESCE(SUM(conversions),0) as conversions')
            ->first();

        return $this->withDerived([
            'impressions' => (int) ($row->impressions ?? 0),
            'clicks' => (int) ($row->clicks ?? 0),
            'spend' => (float) ($row->spend ?? 0),
            'conversions' => (int) ($row->conversions ?? 0),
        ]);
    }

    /**
     * Aggregate totals for many campaigns in one query.
     *
     * @param  array<int, int>  $campaignIds
     * @return array<int, array{impressions: int, clicks: int, spend: float, conversions: int, ctr: float, cpc: float}>
     */
    public function forCampaignIds(array $campaignIds): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $campaignIds)));
        $totals = [];

        foreach ($ids as $id)
        {
            $totals[$id] = $this->emptyTotals();
        }

        if ($ids === [])
        {
            return [];
        }

        $rows = PaidAdMetricSnapshot::query()
            ->join('paid_ad_campaign_platforms', 'paid_ad_campaign_platforms.id', '=', 'paid_ad_metric_snapshots.paid_ad_campaign_platform_id')
            ->whereIn('paid_ad_campaign_platforms.paid_ad_campaign_id', $ids)
            ->groupBy('paid_ad_campaign_platforms.paid_ad_campaign_id')
            ->select('paid_ad_campaign_platforms.paid_ad_campaign_id as campaign_id')
            ->selectRaw('COALESCE(SUM(paid_ad_metric_snapshots.impressions),0) as impressions, COALESCE(SUM(paid_ad_metric_snapshots.clicks),0) as clicks, COALESCE(SUM(paid_ad_metric_snapshots.spend),0) as spend, COALESCE(SUM(paid_ad_metric_snapshots.conversions),0) as conversions')
            ->get();

        foreach ($rows as $row)
        {
            $totals[(int) $row->campaign_id] = $this->withDerived([
                'impressions' => (int) $row->impressions,
                'clicks' => (int) $row->clicks,
                'spend' => (float) $row->spend,
                'conversions' => (int) $row->conversions,
            ]);
        }

        return $totals;
    }

    /**
     * Aggregate totals for the whole team (all campaigns).
     *
     * @return array{impressions: int, clicks: int, spend: float, conversions: int, ctr: float, cpc: float}
     */
    public function forTeam(int $teamId): array
    {
        $row = PaidAdMetricSnapshot::query()
            ->join('paid_ad_campaign_platforms', 'paid_ad_campaign_platforms.id', '=', 'paid_ad_metric_snapshots.paid_ad_campaign_platform_id')
            ->join('paid_ad_campaigns', 'paid_ad_campaigns.id', '=', 'paid_ad_campaign_platforms.paid_ad_campaign_id')
            ->where('paid_ad_campaigns.team_id', $teamId)
            ->selectRaw('COALESCE(SUM(paid_ad_metric_snapshots.impressions),0) as impressions, COALESCE(SUM(paid_ad_metric_snapshots.clicks),0) as clicks, COALESCE(SUM(paid_ad_metric_snapshots.spend),0) as spend, COALESCE(SUM(paid_ad_metric_snapshots.conversions),0) as conversions')
            ->first();

        return $this->withDerived([
            'impressions' => (int) ($row->impressions ?? 0),
            'clicks' => (int) ($row->clicks ?? 0),
            'spend' => (float) ($row->spend ?? 0),
            'conversions' => (int) ($row->conversions ?? 0),
        ]);
    }

    /**
     * Spend breakdown per platform for a team.
     *
     * @return array<int, array{platform: string, impressions: int, clicks: int, spend: float, conversions: int}>
     */
    public function breakdownByPlatform(int $teamId): array
    {
        return PaidAdMetricSnapshot::query()
            ->join('paid_ad_campaign_platforms', 'paid_ad_campaign_platforms.id', '=', 'paid_ad_metric_snapshots.paid_ad_campaign_platform_id')
            ->join('paid_ad_campaigns', 'paid_ad_campaigns.id', '=', 'paid_ad_campaign_platforms.paid_ad_campaign_id')
            ->where('paid_ad_campaigns.team_id', $teamId)
            ->groupBy('paid_ad_campaign_platforms.platform')
            ->select('paid_ad_campaign_platforms.platform')
            ->selectRaw('COALESCE(SUM(paid_ad_metric_snapshots.impressions),0) as impressions, COALESCE(SUM(paid_ad_metric_snapshots.clicks),0) as clicks, COALESCE(SUM(paid_ad_metric_snapshots.spend),0) as spend, COALESCE(SUM(paid_ad_metric_snapshots.conversions),0) as conversions')
            ->get()
            ->map(fn ($row): array => [
                'platform' => (string) $row->platform,
                'impressions' => (int) $row->impressions,
                'clicks' => (int) $row->clicks,
                'spend' => (float) $row->spend,
                'conversions' => (int) $row->conversions,
            ])
            ->all();
    }

    /**
     * @return array{impressions: int, clicks: int, spend: float, conversions: int, ctr: float, cpc: float}
     */
    private function emptyTotals(): array
    {
        return $this->withDerived([
            'impressions' => 0,
            'clicks' => 0,
            'spend' => 0.0,
            'conversions' => 0,
        ]);
    }

    /**
     * @param  array{impressions: int, clicks: int, spend: float, conversions: int}  $totals
     * @return array{impressions: int, clicks: int, spend: float, conversions: int, ctr: float, cpc: float}
     */
    private function withDerived(array $totals): array
    {
        $totals['ctr'] = $totals['impressions'] > 0
            ? round(($totals['clicks'] / $totals['impressions']) * 100, 2)
            : 0.0;

        $totals['cpc'] = $totals['clicks'] > 0
            ? round($totals['spend'] / $totals['clicks'], 2)
            : 0.0;

        return $totals;
    }
}
