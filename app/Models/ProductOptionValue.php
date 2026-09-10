<?php

namespace App\Models;

use App\Support\ShopCatalogApiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductOptionValue extends Model
{
    protected $fillable = [
        'team_id',
        'product_option_id',
        'value',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        $bumpCatalog = function (self $value): void
        {
            $teamId = (int) ($value->team_id ?: 0);
            if ($teamId <= 0 && $value->product_option_id)
            {
                $teamId = (int) ProductOption::query()
                    ->whereKey($value->product_option_id)
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

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_option_values');
    }
}
