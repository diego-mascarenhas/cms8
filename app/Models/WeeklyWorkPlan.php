<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyWorkPlan extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'week_starts_on',
        'challenge',
        'items',
        'review',
        'reviewed_at',
    ];

    protected $casts = [
        'week_starts_on' => 'date',
        'items' => 'array',
        'review' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
