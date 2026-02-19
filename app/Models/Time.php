<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Time extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'user_id',
        'task_id',
        'description',
        'start_time',
        'end_time',
        'duration_seconds',
        'is_billable',
        'hourly_rate',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_billable' => 'boolean',
        'hourly_rate' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $teamId = optional(auth()->user()->currentTeam)->id
                    ?? auth()->user()->teams()->value('teams.id');
                if ($teamId)
                {
                    $builder->where('team_id', $teamId);
                }
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Calculate duration in seconds from start and end time (never negative)
     */
    public function calculateDuration()
    {
        if ($this->start_time && $this->end_time)
        {
            $seconds = (int) $this->end_time->getTimestamp() - (int) $this->start_time->getTimestamp();
            $this->duration_seconds = max(0, $seconds);
            $this->save();
        }
    }

    /**
     * Get formatted duration (e.g., "2h 30m"). Never returns negative values.
     */
    public function getFormattedDurationAttribute()
    {
        $seconds = max(0, (int) $this->duration_seconds);

        if ($seconds === 0)
        {
            return '0m';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0)
        {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    /**
     * Get duration in hours (decimal). Never negative.
     */
    public function getDurationHoursAttribute()
    {
        $seconds = max(0, (int) $this->duration_seconds);

        return $seconds ? round($seconds / 3600, 2) : 0;
    }

    /**
     * Calculate earnings based on hourly rate. Uses non-negative duration only.
     */
    public function getEarningsAttribute()
    {
        $seconds = max(0, (int) $this->duration_seconds);
        if (! $this->is_billable || ! $this->hourly_rate || ! $seconds)
        {
            return 0;
        }

        return round(($seconds / 3600) * $this->hourly_rate, 2);
    }

    /**
     * Check if timer is currently running
     */
    public function isRunning()
    {
        return $this->start_time && ! $this->end_time;
    }

    /**
     * Stop the timer
     */
    public function stop()
    {
        if ($this->isRunning())
        {
            $this->end_time = now();
            $this->calculateDuration();

            return true;
        }

        return false;
    }

    /**
     * Get currently running timer for user
     */
    public static function getRunningTimer($userId = null)
    {
        $userId = $userId ?? auth()->id();

        return static::where('user_id', $userId)
            ->whereNull('end_time')
            ->first();
    }

    /**
     * Configure activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['task_id', 'description', 'start_time', 'end_time', 'duration_seconds', 'is_billable'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
