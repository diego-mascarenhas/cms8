<?php

namespace App\Models;

use App\Enums\ShoppingCartChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'session_key',
        'channel',
    ];

    protected $casts = [
        'channel' => ShoppingCartChannel::class,
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

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingCartItem::class);
    }

    public function totalAmount(): float
    {
        return round((float) $this->items->sum(fn (ShoppingCartItem $item): float => $item->lineTotal()), 2);
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
