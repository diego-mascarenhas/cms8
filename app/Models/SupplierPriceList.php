<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPriceList extends Model
{
    protected $fillable = [
        'team_id',
        'enterprise_id',
        'name',
        'currency_id',
        'markup_percent',
        'valid_from',
        'valid_until',
        'imported_at',
    ];

    protected $casts = [
        'markup_percent' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'imported_at' => 'datetime',
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

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class)->withoutGlobalScope('team');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierPriceLine::class);
    }
}
