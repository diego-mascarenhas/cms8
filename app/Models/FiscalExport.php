<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalExport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_EXPORTED = 'exported';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_RECTIFIED = 'rectified';

    protected $fillable = [
        'team_id',
        'invoice_id',
        'platform',
        'external_id',
        'external_number',
        'external_customer_id',
        'status',
        'attempts',
        'error_message',
        'payload_hash',
        'payload_snapshot',
        'response_snapshot',
        'exported_at',
        'last_attempted_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'payload_snapshot' => 'array',
        'response_snapshot' => 'array',
        'exported_at' => 'datetime',
        'last_attempted_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function isExported(): bool
    {
        return $this->status === self::STATUS_EXPORTED;
    }
}
