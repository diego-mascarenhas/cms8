<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_id',
        'stripe_product',
        'stripe_price',
        'name',
        'description',
        'active',
        'category',
        'plan',
        'type',
        'currency',
        'unit_amount',
        'recurring_interval',
        'recurring_interval_count',
        'metadata',
        'last_synced_at',
        'raw_payload',
    ];

    protected $casts = [
        'active' => 'boolean',
        'unit_amount' => 'decimal:2',
        'recurring_interval_count' => 'integer',
        'metadata' => 'array',
        'raw_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Get formatted price for display
     */
    public function getFormattedPrice(): string
    {
        if (! $this->unit_amount)
        {
            return '—';
        }

        $amount = $this->unit_amount / 100; // Convert from cents
        $currency = strtoupper($this->currency ?? 'USD');

        return number_format($amount, 2, ',', '.').' '.$currency;
    }

    /**
     * Get billing frequency description
     */
    public function getBillingFrequency(): ?string
    {
        if (! $this->recurring_interval)
        {
            return null;
        }

        $count = $this->recurring_interval_count ?? 1;

        return match ($this->recurring_interval)
        {
            'month' => $count > 1 ? "Cada {$count} meses" : 'Mensual',
            'year' => $count > 1 ? "Cada {$count} años" : 'Anual',
            'week' => $count > 1 ? "Cada {$count} semanas" : 'Semanal',
            'day' => $count > 1 ? "Cada {$count} días" : 'Diario',
            default => ucfirst($this->recurring_interval),
        };
    }

    /**
     * Get Stripe price ID for use with Cashier
     */
    public function getStripePriceId(): ?string
    {
        return $this->stripe_price;
    }
}
