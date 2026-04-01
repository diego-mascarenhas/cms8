<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'contact_id',
        'responsible_id',
        'opportunity_stage_id',
        'name',
        'opened_at',
        'estimated_amount',
        'currency_id',
        'description',
        'notes',
        'offering_summary',
        'offering_type',
        'offering_id',
        'expected_close_at',
        'probability',
        'won_project_id',
        'closed_at',
        'closed_reason',
    ];

    protected $casts = [
        'opened_at' => 'date',
        'expected_close_at' => 'date',
        'closed_at' => 'datetime',
        'estimated_amount' => 'decimal:2',
        'probability' => 'integer',
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

        static::addGlobalScope('ownership', function (Builder $builder): void
        {
            if (auth()->check())
            {
                $user = auth()->user();
                if (! $user->hasRole('admin'))
                {
                    $builder->where('responsible_id', $user->id);
                }
            }
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(OpportunityStage::class, 'opportunity_stage_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function wonProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'won_project_id');
    }

    public function offering(): MorphTo
    {
        return $this->morphTo();
    }

    public function interactions(): MorphMany
    {
        return $this->morphMany(ContactInteraction::class, 'relatable');
    }
}
