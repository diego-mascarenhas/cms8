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
     * Display labels for category column (subscription product categories).
     *
     * @return array<string, string>
     */
    public static function getCategoryLabels(): array
    {
        return [
            'backups' => __('Backups'),
            'domain' => __('Domain'),
            'hosting' => __('Hosting'),
            'mailer' => __('Mailer'),
            'mentoring' => __('Mentoring'),
            'prospecting' => __('Prospectos'),
            'support' => __('Support'),
            'vps' => __('VPS'),
            'web_cloud' => __('Web Cloud'),
            'whatsapp' => __('WhatsApp'),
        ];
    }

    /**
     * Get display label for a category.
     */
    public static function getCategoryLabel(?string $category): string
    {
        if (! $category)
        {
            return '—';
        }

        $labels = static::getCategoryLabels();

        return $labels[$category] ?? $category;
    }

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

        $currency = strtoupper($this->currency ?? 'USD');

        return number_format((float) $this->unit_amount, 2, ',', '.').' '.$currency;
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

    /**
     * Get Stripe price ID for a mailer plan (basic, foundation, scale) from subscription_products.
     */
    public static function getMailerPriceId(string $plan): ?string
    {
        $product = static::active()
            ->where('category', 'mailer')
            ->where('plan', $plan)
            ->first();

        return $product?->stripe_price;
    }

    /**
     * Get Stripe product ID for a mailer plan from subscription_products.
     */
    public static function getMailerProductId(string $plan): ?string
    {
        $product = static::active()
            ->where('category', 'mailer')
            ->where('plan', $plan)
            ->first();

        return $product?->stripe_product;
    }

    /**
     * Get Stripe price ID for a prospect recurring plan (basic, growth) from subscription_products.
     */
    public static function getProspectRecurringPriceId(string $plan): ?string
    {
        $product = static::active()
            ->where('category', 'prospecting')
            ->whereNotNull('recurring_interval')
            ->where('plan', $plan)
            ->first();

        return $product?->stripe_price;
    }

    /**
     * Get Stripe price ID for the one-time Prospection product from subscription_products.
     */
    public static function getProspectionPriceId(): ?string
    {
        $product = static::active()
            ->where('category', 'prospecting')
            ->whereNull('recurring_interval')
            ->first();

        return $product?->stripe_price;
    }

    /**
     * Find subscription product by Stripe price ID (any category).
     */
    public static function findByStripePrice(string $stripePrice): ?static
    {
        return static::active()
            ->where('stripe_price', $stripePrice)
            ->first();
    }

    /**
     * Get the SLA for this subscription product.
     */
    public function sla()
    {
        return $this->hasOne(SLA::class)->where('is_active', true)->latest();
    }

    /**
     * Get all SLAs for this subscription product.
     */
    public function slas()
    {
        return $this->hasMany(SLA::class);
    }
}
