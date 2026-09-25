<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoLink extends Model
{
    use HasFactory;

    public const TYPE_SHOP_CATALOG_QR = 'shop_catalog_qr';

    protected $fillable = [
        'team_id',
        'code',
        'type',
        'target_url',
        'active',
        'hits_count',
        'last_hit_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'hits_count' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function hits(): HasMany
    {
        return $this->hasMany(GoLinkHit::class);
    }

    public function publicUrl(): string
    {
        return rtrim((string) config('services.go.url', 'https://go.idoneo.dev'), '/').'/q/'.$this->code;
    }
}
