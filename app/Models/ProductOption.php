<?php

namespace App\Models;

use App\Support\ShopCatalogApiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends Model
{
    protected $fillable = [
        'team_id',
        'product_id',
        'name',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        $bumpCatalog = function (self $option): void
        {
            $teamId = (int) ($option->team_id ?: 0);
            if ($teamId <= 0 && $option->product_id)
            {
                $teamId = (int) Product::withoutGlobalScope('team')
                    ->whereKey($option->product_id)
                    ->value('team_id');
            }

            if ($teamId > 0)
            {
                ShopCatalogApiCache::bumpTeam($teamId);
            }
        };

        static::saved($bumpCatalog);
        static::deleted($bumpCatalog);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('position')->orderBy('value');
    }
}
