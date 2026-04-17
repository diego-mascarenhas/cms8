<?php

namespace App\Models;

use App\Enums\ExternalProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'provider',
        'provider_user_id',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'scopes',
        'provider_metadata',
        'last_synced_at',
    ];

    protected $casts = [
        'provider' => ExternalProvider::class,
        'scopes' => 'array',
        'provider_metadata' => 'array',
        'access_token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cursors(): HasMany
    {
        return $this->hasMany(SyncCursor::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }
}
