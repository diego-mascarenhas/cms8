<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessCreationAiLog extends Model
{
    protected $fillable = [
        'business_creation_session_id',
        'type',
        'request_payload',
        'response_payload',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(BusinessCreationSession::class, 'business_creation_session_id');
    }
}
