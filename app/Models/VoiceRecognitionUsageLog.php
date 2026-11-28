<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceRecognitionUsageLog extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'source',
        'external_ref',
        'seconds',
        'recognized_at',
    ];

    protected $casts = [
        'seconds' => 'integer',
        'recognized_at' => 'datetime',
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
