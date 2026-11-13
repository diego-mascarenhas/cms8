<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamSnapshot extends Model
{
    public const TYPE_FINANCE_DASHBOARD = 'finance.dashboard';

    public const TYPE_MAILER_STATS = 'mailer.stats';

    protected $fillable = [
        'team_id',
        'type',
        'key',
        'payload',
        'captured_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'captured_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeWithKey(Builder $query, ?string $key): Builder
    {
        if ($key === null || $key === '')
        {
            return $query->whereNull('key');
        }

        return $query->where('key', $key);
    }
}
