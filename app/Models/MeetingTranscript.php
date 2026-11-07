<?php

namespace App\Models;

use App\Enums\MeetingTranscriptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MeetingTranscript extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'title',
        'external_ref',
        'original_filename',
        'audio_path',
        'language',
        'status',
        'transcript',
        'turns',
        'summary',
        'public_token',
        'calendar_event_id',
        'error',
    ];

    protected $casts = [
        'status' => MeetingTranscriptStatus::class,
        'turns' => 'array',
        'summary' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function billedAudioSeconds(): int
    {
        $end = 0.0;

        foreach ($this->turns ?? [] as $turn)
        {
            if (! is_array($turn))
            {
                continue;
            }

            $end = max($end, (float) ($turn['end_seconds'] ?? 0));
        }

        return (int) ceil($end);
    }

    public function publicToken(): string
    {
        if (is_string($this->public_token) && $this->public_token !== '')
        {
            return $this->public_token;
        }

        $token = Str::random(40);
        $this->forceFill(['public_token' => $token])->save();

        return $token;
    }
}
