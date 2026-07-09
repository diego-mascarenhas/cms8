<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidAdMetricSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'paid_ad_campaign_platform_id',
        'date',
        'impressions',
        'clicks',
        'spend',
        'conversions',
        'ctr',
        'cpc',
        'raw',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'spend' => 'decimal:2',
        'conversions' => 'integer',
        'ctr' => 'decimal:4',
        'cpc' => 'decimal:4',
        'raw' => 'array',
    ];

    public function campaignPlatform(): BelongsTo
    {
        return $this->belongsTo(PaidAdCampaignPlatform::class, 'paid_ad_campaign_platform_id');
    }
}
