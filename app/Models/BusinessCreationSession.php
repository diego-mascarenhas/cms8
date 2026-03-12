<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BusinessCreationSession extends Model
{
    protected $fillable = [
        'token',
        'team_id',
        'user_id',
        'config',
        'current_step',
        'completed_at',
    ];

    protected $casts = [
        'config' => 'array',
        'completed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(BusinessCreationAiLog::class);
    }

    /**
     * Create a new session with a unique token (for public landing).
     */
    public static function createWithToken(?int $teamId = null, ?int $userId = null): self
    {
        return self::create([
            'token' => Str::random(48),
            'team_id' => $teamId,
            'user_id' => $userId,
            'config' => [],
            'current_step' => 1,
        ]);
    }
}
