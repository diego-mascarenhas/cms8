<?php

namespace App\Models;

use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Services\PaidAds\PaidAdCampaignCalendarSyncer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaidAdCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'created_by',
        'name',
        'objective',
        'status',
        'budget_type',
        'budget_amount',
        'currency',
        'start_at',
        'end_at',
        'targeting',
        'creative',
        'settings',
        'calendar_event_id',
    ];

    protected $casts = [
        'objective' => PaidAdObjective::class,
        'status' => PaidAdCampaignStatus::class,
        'budget_amount' => 'decimal:2',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'targeting' => 'array',
        'creative' => 'array',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function (PaidAdCampaign $model): void
        {
            if (auth()->check())
            {
                if (! $model->team_id)
                {
                    $model->team_id = auth()->user()->currentTeam->id;
                }

                if (! $model->created_by)
                {
                    $model->created_by = auth()->id();
                }
            }
        });

        static::saved(function (PaidAdCampaign $model): void
        {
            app(PaidAdCampaignCalendarSyncer::class)->sync(
                $model->fresh(['platforms', 'team']) ?? $model,
            );
        });

        static::deleting(function (PaidAdCampaign $model): void
        {
            app(PaidAdCampaignCalendarSyncer::class)->forget($model);
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function platforms(): HasMany
    {
        return $this->hasMany(PaidAdCampaignPlatform::class);
    }

    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(PaidAdAudience::class, 'paid_ad_audience_campaign')
            ->withTimestamps();
    }

    public function totalSpend(): float
    {
        return (float) $this->platforms()
            ->join('paid_ad_metric_snapshots', 'paid_ad_metric_snapshots.paid_ad_campaign_platform_id', '=', 'paid_ad_campaign_platforms.id')
            ->sum('paid_ad_metric_snapshots.spend');
    }
}
