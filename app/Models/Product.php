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
        'status',
        'catalog_status',
        'stock_status',
        'manage_stock',
        'stock_quantity',
        'size_options',
        'color_options',
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
        'size_options' => 'array',
        'color_options' => 'array',
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
