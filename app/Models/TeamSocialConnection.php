<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamSocialConnection extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'source_id',
        'provider',
        'external_account_id',
        'access_token',
        'refresh_token',
        'aux_secret',
        'access_token_expires_at',
        'scopes',
        'metadata',
        'status',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'scopes' => 'array',
        'metadata' => 'array',
        'access_token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'aux_secret',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }
}
