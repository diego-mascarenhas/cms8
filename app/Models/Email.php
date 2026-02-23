<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Email extends Model
{
    protected $fillable = [
        'mailbox_id',
        'team_id',
        'message_id',
        'subject',
        'body_text',
        'body_html',
        'from_address',
        'to_address',
        'message_date',
        'seen',
        'flagged',
    ];

    protected $casts = [
        'message_date' => 'datetime',
        'seen' => 'boolean',
        'flagged' => 'boolean',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
