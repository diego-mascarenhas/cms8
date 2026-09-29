<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectUsageLog extends Model
{
    /** @use HasFactory<\Database\Factories\ProspectUsageLogFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'source',
        'count',
        'consumed_at',
    ];

    protected $casts = [
        'count' => 'integer',
        'consumed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
