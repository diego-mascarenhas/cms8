<?php

namespace App\Models;

use App\Enums\ProductStockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'product_id',
        'sku',
        'price',
        'sale_price',
        'stock_status',
        'manage_stock',
        'stock_quantity',
        'is_default',
        'position',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'manage_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'is_default' => 'boolean',
        'position' => 'integer',
        'stock_status' => ProductStockStatus::class,
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_option_values');
    }

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

    public function optionLabel(): string
    {
        $this->loadMissing('optionValues.option');

        return $this->optionValues
            ->sortBy(fn (ProductOptionValue $value): int => (int) $value->option?->position)
            ->map(fn (ProductOptionValue $value): string => (string) $value->value)
            ->filter()
            ->implode(' / ');
    }

    public function displayName(?string $productName = null): string
    {
        $base = $productName ?? $this->product?->name ?? '';
        $label = $this->optionLabel();

        return $label !== '' ? trim($base.' · '.$label) : $base;
    }
}
