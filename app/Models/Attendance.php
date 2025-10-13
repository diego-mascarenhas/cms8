<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'start_at',
        'end_at',
        'paused_at',
        'paused_seconds',
        'duration_seconds',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
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

    public function isRunning(): bool
    {
        return $this->start_at && ! $this->end_at;
    }

    public function stop(): bool
    {
        if (! $this->isRunning())
        {
            return false;
        }

        $this->end_at = now();
        $total = $this->end_at->diffInSeconds($this->start_at);
        $paused = (int) ($this->paused_seconds ?? 0);
        $this->duration_seconds = max(0, $total - $paused);
        $this->save();

        return true;
    }

    public function pause(): bool
    {
        if (! $this->isRunning() || $this->paused_at)
        {
            return false;
        }
        $this->paused_at = now();
        $this->save();

        return true;
    }

    public function resume(): bool
    {
        if (! $this->isRunning() || ! $this->paused_at)
        {
            return false;
        }
        $pausedSoFar = (int) ($this->paused_seconds ?? 0);
        $pausedSoFar += now()->diffInSeconds($this->paused_at);
        $this->paused_seconds = $pausedSoFar;
        $this->paused_at = null;
        $this->save();

        return true;
    }

    public static function getRunning(?int $userId = null)
    {
        $userId = $userId ?? auth()->id();

        return static::where('user_id', $userId)
            ->whereNull('end_at')
            ->first();
    }
}
