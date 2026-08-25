<?php

namespace App\Models;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'short_description',
        'price',
        'sale_price',
        'currency_id',
        'store_id',
        'category_id',
        'brand_id',
        'status',
        'catalog_status',
        'stock_status',
        'manage_stock',
        'stock_quantity',
        'assortment_size',
        'whatsapp_enabled',
        'team_id',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'status' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'manage_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'assortment_size' => 'integer',
        'catalog_status' => ProductCatalogStatus::class,
        'stock_status' => ProductStockStatus::class,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::saving(function (Product $product): void
        {
            if ($product->catalog_status === null)
            {
                $product->catalog_status = $product->status
                    ? ProductCatalogStatus::Publish
                    : ProductCatalogStatus::Draft;
            }

            $product->status = $product->catalog_status === ProductCatalogStatus::Publish;
        });
    }

    /**
     * Price used for cart and listings (sale when valid, otherwise regular).
     */
    public function currentSellingPrice(): float
    {
        $variant = $this->defaultVariant();
        if ($variant)
        {
            return $variant->currentSellingPrice();
        }

        $regular = (float) $this->price;
        $sale = $this->sale_price !== null ? (float) $this->sale_price : null;
        if ($sale !== null && $sale > 0 && $sale < $regular)
        {
            return $sale;
        }

        return $regular;
    }

    public function isOnSale(): bool
    {
        $regular = (float) $this->price;
        $sale = $this->sale_price !== null ? (float) $this->sale_price : null;

        return $sale !== null && $sale > 0 && $sale < $regular;
    }

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the currency that owns the product.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the store that owns the product.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class)->orderBy('position')->orderBy('name');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    public function defaultVariant(): ?ProductVariant
    {
        $this->loadMissing('variants');

        return $this->variants->firstWhere('is_default', true) ?? $this->variants->first();
    }

    /**
     * @return list<string>
     */
    public function optionValuesNamed(string $name): array
    {
        $this->loadMissing('options.values');
        $needle = mb_strtolower($name);
        $option = $this->options->first(
            fn (ProductOption $option): bool => mb_strtolower($option->name) === $needle,
        );

        return $option?->values->pluck('value')->values()->all() ?? [];
    }

    /**
     * Get the team that owns the product.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to only include WhatsApp enabled products.
     */
    public function scopeWhatsAppEnabled($query)
    {
        return $query->where('whatsapp_enabled', true);
    }
}
