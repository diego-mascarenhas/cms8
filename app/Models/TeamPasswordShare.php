<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TeamPasswordShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_password_id',
        'token_hash',
        'max_views',
        'views_count',
        'expires_at',
        'consumed_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function password(): BelongsTo
    {
        return $this->belongsTo(TeamPassword::class, 'team_password_id')
            ->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon && now()->greaterThan($this->expires_at);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null || $this->views_count >= $this->max_views;
    }
}
