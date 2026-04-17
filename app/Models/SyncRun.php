<?php

namespace App\Models;

use App\Enums\SyncResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_account_id',
        'resource',
        'status',
        'pulled_count',
        'upserted_count',
        'deleted_count',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'resource' => SyncResource::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function externalAccount(): BelongsTo
    {
        return $this->belongsTo(ExternalAccount::class);
    }
}
