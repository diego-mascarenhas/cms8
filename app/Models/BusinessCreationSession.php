<?php

namespace App\Models;

use Carbon\Carbon;
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
     * Build metadata for AI logs: step_history and time spent per step (seconds).
     *
     * @return array{step_history: array<int, array{step: int, at: string}>, step_durations_seconds: array<int, int>, total_duration_seconds: int}
     */
    public function getStepMetadata(): array
    {
        $history = $this->config['_step_history'] ?? [];
        if ($history === [])
        {
            return [
                'step_history' => [],
                'step_durations_seconds' => [],
                'total_duration_seconds' => 0,
            ];
        }
        $durations = [];
        $total = 0;
        for ($i = 0; $i < count($history) - 1; $i++)
        {
            $cur = $history[$i];
            $next = $history[$i + 1];
            $step = (int) $cur['step'];
            $from = Carbon::parse($cur['at']);
            $to = Carbon::parse($next['at']);
            $sec = (int) $from->diffInSeconds($to);
            $durations[$step] = ($durations[$step] ?? 0) + $sec;
            $total += $sec;
        }

        return [
            'step_history' => $history,
            'step_durations_seconds' => $durations,
            'total_duration_seconds' => $total,
        ];
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
