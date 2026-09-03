<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailerUsageLog extends Model
{
    /** @use HasFactory<\Database\Factories\MailerUsageLogFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'source',
        'count',
        'sent_at',
    ];

    protected $casts = [
        'count' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
