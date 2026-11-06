<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingVoiceProfile extends Model
{
    protected $fillable = [
        'team_id',
        'contact_id',
        'provider_speaker_id',
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
