<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shopping_cart_id',
        'team_id',
        'product_id',
        'name',
        'price',
        'quantity',
        'currency_id',
        'store_id',
        'category_name',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
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

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShoppingCart::class, 'shopping_cart_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function lineTotal(): float
    {
        return round((float) $this->price * (int) $this->quantity, 2);
    }

    /**
     * Shape used by checkout and WhatsApp copy (product id in `id`).
     */
    public function toLineObject(): object
    {
        return (object) [
            'id' => (int) $this->product_id,
            'name' => (string) $this->name,
            'price' => (float) $this->price,
            'quantity' => (int) $this->quantity,
            'attributes' => (object) [
                'team_id' => (int) $this->team_id,
                'store_id' => $this->store_id,
                'currency_id' => $this->currency_id,
                'description' => $this->description,
                'category_name' => $this->category_name ?? '',
            ],
        ];
    }
}
