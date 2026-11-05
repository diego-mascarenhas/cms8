<?php

namespace App\Models;

use App\Enums\MeetingTranscriptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingTranscript extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'title',
        'original_filename',
        'audio_path',
        'language',
        'status',
        'transcript',
        'turns',
        'error',
    ];

    protected $casts = [
        'status' => MeetingTranscriptStatus::class,
        'turns' => 'array',
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
}
