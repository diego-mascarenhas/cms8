<?php

namespace App\Services;

use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;

class DashboardAnalyticsService
{
    private const CACHE_SECONDS = 3600;

    private const FAILURE_CACHE_SECONDS = 120;

    /**
     * @return array{
     *     dates: list<mixed>,
     *     visitors: list<int>,
     *     pageViews: list<int>,
     *     totals: array{visitors: int, page_views: int, new_users: int, returning_users: int},
     *     top_pages: list<array{title: string, url: string, views: int}>,
     *     top_countries: list<array{country: string, views: int}>
     * }|null
     */
    public function forTeam(?Team $team): ?array
    {
        if ($team === null)
        {
            return null;
        }

        $propertyId = $team->getSetting('analytics_property_id');
        $credentialsJson = $team->getSetting('analytics_credentials_json');

        if (! $propertyId || ! $credentialsJson)
        {
            return null;
        }

        $failureKey = "dashboard.analytics.v4.failed.{$team->id}";

        if (Cache::has($failureKey))
        {
            return null;
        }

        $payload = Cache::remember(
            "dashboard.analytics.v4.{$team->id}",
            self::CACHE_SECONDS,
            function () use ($propertyId, $credentialsJson)
            {
                $credentials = is_string($credentialsJson) ? json_decode($credentialsJson, true) : $credentialsJson;
                if (! is_array($credentials))
                {
                    return null;
                }

                config([
                    'analytics.property_id' => $propertyId,
                    'analytics.service_account_credentials_json' => $credentials,
                ]);

                try
                {
                    $period = Period::days(30);
                    $collection = Analytics::fetchTotalVisitorsAndPageViews($period, 30)
                        ->sortBy(fn ($row) => ($row['date'] ?? null) instanceof Carbon
                            ? $row['date']->timestamp
                            : strtotime((string) ($row['date'] ?? '')))
                        ->values();

                    $dates = $collection->pluck('date')->map(fn ($d) => $d instanceof Carbon ? $d->format('Y-m-d') : $d)->values()->all();
                    $visitors = $collection->pluck('activeUsers')->map(fn ($v) => (int) $v)->values()->all();
                    $pageViews = $collection->pluck('screenPageViews')->map(fn ($v) => (int) $v)->values()->all();
                    $userTypes = $this->userTypeCounts($period);

                    return [
                        'dates' => $dates,
                        'visitors' => $visitors,
                        'pageViews' => $pageViews,
                        'totals' => [
                            'visitors' => array_sum($visitors),
                            'page_views' => array_sum($pageViews),
                            'new_users' => $userTypes['new'],
                            'returning_users' => $userTypes['returning'],
                        ],
                        'top_pages' => $this->topPages($period),
                        'top_countries' => $this->topCountries($period),
                    ];
                } catch (\Throwable $e)
                {
                    \Log::warning('Dashboard Google Analytics fetch failed: '.$e->getMessage());

                    return null;
                }
            },
        );

        if ($payload === null)
        {
            Cache::put($failureKey, true, self::FAILURE_CACHE_SECONDS);
        }

        return $payload;
    }

    /**
     * @return list<array{title: string, url: string, views: int}>
     */
    private function topPages(Period $period): array
    {
        try
        {
            return Analytics::fetchMostVisitedPages($period, 5)
                ->map(fn ($row): array => [
                    'title' => (string) ($row['pageTitle'] ?? __('Sin título')),
                    'url' => (string) ($row['fullPageUrl'] ?? ''),
                    'views' => (int) ($row['screenPageViews'] ?? 0),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e)
        {
            \Log::warning('Dashboard GA top pages failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return list<array{country: string, views: int}>
     */
    private function topCountries(Period $period): array
    {
        try
        {
            return Analytics::fetchTopCountries($period, 5)
                ->map(fn ($row): array => [
                    'country' => (string) ($row['country'] ?? __('Desconocido')),
                    'views' => (int) ($row['screenPageViews'] ?? 0),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e)
        {
            \Log::warning('Dashboard GA top countries failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return array{new: int, returning: int}
     */
    private function userTypeCounts(Period $period): array
    {
        $counts = ['new' => 0, 'returning' => 0];

        try
        {
            foreach (Analytics::fetchUserTypes($period) as $row)
            {
                $type = strtolower((string) ($row['newVsReturning'] ?? ''));
                if (array_key_exists($type, $counts))
                {
                    $counts[$type] = (int) ($row['activeUsers'] ?? 0);
                }
            }
        } catch (\Throwable $e)
        {
            \Log::warning('Dashboard GA user types failed: '.$e->getMessage());
        }

        return $counts;
    }
}
