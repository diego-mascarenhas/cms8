<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventSyncMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_account_id',
        'calendar_event_id',
        'external_id',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function externalAccount(): BelongsTo
    {
        return $this->belongsTo(ExternalAccount::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }
}
