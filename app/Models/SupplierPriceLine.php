<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceLine extends Model
{
    protected $fillable = [
        'team_id',
        'supplier_price_list_id',
        'product_id',
        'supplier_code',
        'name',
        'unit_cost',
        'min_qty',
        'unit',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'min_qty' => 'decimal:2',
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

    public function list(): BelongsTo
    {
        return $this->belongsTo(SupplierPriceList::class, 'supplier_price_list_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
