<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonEnrichment extends Model
{
    protected $fillable = [
        'person_id',
        'source',
        'external_id',
        'payload',
        'fetched_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
