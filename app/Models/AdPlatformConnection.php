<?php

namespace App\Models;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPlatformConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'platform',
        'external_account_id',
        'ad_account_id',
        'ad_account_name',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'scopes',
        'metadata',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'platform' => AdPlatform::class,
        'status' => AdConnectionStatus::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'scopes' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
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

        static::creating(function (AdPlatformConnection $model): void
        {
            if (! $model->team_id && auth()->check())
            {
                $model->team_id = auth()->user()->currentTeam->id;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaignPlatforms(): HasMany
    {
        return $this->hasMany(PaidAdCampaignPlatform::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->access_token_expires_at !== null
            && $this->access_token_expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === AdConnectionStatus::Active
            && $this->ad_account_id !== null;
    }
}
