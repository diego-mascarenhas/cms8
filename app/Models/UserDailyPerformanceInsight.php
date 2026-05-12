<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyPerformanceInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'insight_date',
        'performance_ratio',
        'headline',
        'focus',
        'message',
        'context_snapshot',
    ];

    protected $casts = [
        'insight_date' => 'date',
        'performance_ratio' => 'decimal:2',
        'context_snapshot' => 'array',
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
