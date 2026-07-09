<?php

namespace App\Models;

use App\Enums\AdPlatform;
use App\Enums\AdPublishStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaidAdCampaignPlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'paid_ad_campaign_id',
        'ad_platform_connection_id',
        'platform',
        'external_campaign_id',
        'publish_status',
        'publish_error',
        'platform_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'platform' => AdPlatform::class,
        'publish_status' => AdPublishStatus::class,
        'platform_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PaidAdCampaign::class, 'paid_ad_campaign_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AdPlatformConnection::class, 'ad_platform_connection_id');
    }

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(PaidAdMetricSnapshot::class);
    }
}
