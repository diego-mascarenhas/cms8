<?php

namespace App\Services\Ads\DTO;

use Carbon\CarbonInterface;

class AdMetricsDTO
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly CarbonInterface $date,
        public readonly int $impressions = 0,
        public readonly int $clicks = 0,
        public readonly float $spend = 0.0,
        public readonly int $conversions = 0,
        public readonly array $raw = [],
    ) {}

    public function ctr(): float
    {
        if ($this->impressions === 0)
        {
            return 0.0;
        }

        return round(($this->clicks / $this->impressions) * 100, 4);
    }

    public function cpc(): float
    {
        if ($this->clicks === 0)
        {
            return 0.0;
        }

        return round($this->spend / $this->clicks, 4);
    }
}
