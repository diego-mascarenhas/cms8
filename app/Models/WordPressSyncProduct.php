<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPressSyncProduct extends Model
{
    protected $table = 'wordpress_sync_products';

    protected $fillable = [
        'team_id',
        'wp_id',
        'name',
        'description',
        'price',
        'currency',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
