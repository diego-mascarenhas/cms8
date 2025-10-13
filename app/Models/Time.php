<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Time extends Model
{
    use HasFactory;
    use LogsActivity;
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
                $builder->where('team_id', auth()->user()->currentTeam->id);
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
     * Calculate duration in seconds from start and end time
     */
    public function calculateDuration()
    {
        if ($this->start_time && $this->end_time)
        {
            $this->duration_seconds = $this->end_time->diffInSeconds($this->start_time);
            $this->save();
        }
    }

    /**
     * Get formatted duration (e.g., "2h 30m")
     */
    public function getFormattedDurationAttribute()
    {
        if (! $this->duration_seconds)
        {
            return '0m';
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);

        if ($hours > 0)
        {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }

    /**
     * Get duration in hours (decimal)
     */
    public function getDurationHoursAttribute()
    {
        return $this->duration_seconds ? round($this->duration_seconds / 3600, 2) : 0;
    }

    /**
     * Calculate earnings based on hourly rate
     */
    public function getEarningsAttribute()
    {
        if (! $this->is_billable || ! $this->hourly_rate || ! $this->duration_seconds)
        {
            return 0;
        }

        return round(($this->duration_seconds / 3600) * $this->hourly_rate, 2);
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
