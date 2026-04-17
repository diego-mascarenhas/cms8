<?php

namespace App\Models;

use App\Enums\SyncResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncCursor extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_account_id',
        'resource',
        'cursor',
        'full_synced_at',
    ];

    protected $casts = [
        'resource' => SyncResource::class,
        'full_synced_at' => 'datetime',
    ];

    public function externalAccount(): BelongsTo
    {
        return $this->belongsTo(ExternalAccount::class);
    }
}
