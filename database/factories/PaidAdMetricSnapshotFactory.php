<?php

namespace Database\Factories;

use App\Models\PaidAdCampaignPlatform;
use App\Models\PaidAdMetricSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaidAdMetricSnapshot>
 */
class PaidAdMetricSnapshotFactory extends Factory
{
    protected $model = PaidAdMetricSnapshot::class;

    public function definition(): array
    {
        $impressions = $this->faker->numberBetween(100, 10000);
        $clicks = $this->faker->numberBetween(1, $impressions);
        $spend = $this->faker->randomFloat(2, 1, 500);

        return [
            'paid_ad_campaign_platform_id' => PaidAdCampaignPlatform::factory(),
            'date' => now()->toDateString(),
            'impressions' => $impressions,
            'clicks' => $clicks,
            'spend' => $spend,
            'conversions' => $this->faker->numberBetween(0, $clicks),
            'ctr' => round(($clicks / $impressions) * 100, 4),
            'cpc' => $clicks > 0 ? round($spend / $clicks, 4) : 0,
            'raw' => [],
        ];
    }
}
