<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoLinkHit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'go_link_id',
        'ip_hash',
        'user_agent',
        'referer',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function goLink(): BelongsTo
    {
        return $this->belongsTo(GoLink::class);
    }
}
