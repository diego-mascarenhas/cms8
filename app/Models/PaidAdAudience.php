<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PaidAdAudience extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'created_by',
        'name',
        'type',
        'targeting_rules',
        'estimated_size',
    ];

    protected $casts = [
        'targeting_rules' => 'array',
        'estimated_size' => 'integer',
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

        static::creating(function (PaidAdAudience $model): void
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
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(PaidAdCampaign::class, 'paid_ad_audience_campaign')
            ->withTimestamps();
    }
}
