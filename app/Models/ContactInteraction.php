<?php

namespace App\Models;

use App\Enums\ContactInteractionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContactInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'user_id',
        'relatable_type',
        'relatable_id',
        'type',
        'subject',
        'body',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'type' => ContactInteractionType::class,
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatable(): MorphTo
    {
        return $this->morphTo();
    }
}
