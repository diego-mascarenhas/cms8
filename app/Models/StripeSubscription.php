<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StripeSubscription extends Model
{
    use HasFactory;

    protected $table = 'stripe_subscriptions';

    protected $fillable = [
        'stripe_id',
        'type',
        'team_id',
        'customer_id',
        'customer_email',
        'customer_name',
        'customer_country',
        'customer_tax_id_type',
        'customer_tax_id',
        'status',
        'collection_method',
        'plan_name',
        'plan_interval',
        'plan_interval_count',
        'quantity',
        'price_currency',
        'unit_amount',
        'amount_subtotal',
        'amount_total',
        'invoice_note',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'last_synced_at',
        'amount_usd',
        'amount_ars',
        'amount_eur',
        'raw_payload',
        'data',
    ];

    protected $casts = [
        'unit_amount' => 'decimal:2',
        'amount_subtotal' => 'decimal:2',
        'amount_total' => 'decimal:2',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'canceled_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'amount_usd' => 'decimal:2',
        'amount_ars' => 'decimal:2',
        'amount_eur' => 'decimal:2',
        'raw_payload' => 'array',
        'data' => 'array',
    ];

    protected $attributes = [
        'type' => 'sell',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Client enterprise: enterprises.code = Stripe customer_id (cus_…), same as account form field enterprise[code].
     */
    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class, 'customer_id', 'code');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(SubscriptionChange::class, 'subscription_id')->latest('detected_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(SubscriptionNotification::class, 'subscription_id')->latest();
    }

    /**
     * Services in Humano linked to this Stripe subscription row (services.subscription_id).
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'subscription_id');
    }

    public function getBillingFrequencyAttribute(): ?string
    {
        if (! $this->plan_interval)
        {
            return null;
        }

        $count = $this->plan_interval_count ?? 1;
        if ($this->plan_interval === 'indefinite')
        {
            return 'Indefinido';
        }

        $intervalMap = [
            'day' => ['singular' => 'día', 'plural' => 'días'],
            'week' => ['singular' => 'semana', 'plural' => 'semanas'],
            'month' => ['singular' => 'mes', 'plural' => 'meses'],
            'quarter' => ['singular' => 'trimestre', 'plural' => 'trimestres'],
            'semester' => ['singular' => 'semestre', 'plural' => 'semestres'],
            'year' => ['singular' => 'año', 'plural' => 'años'],
            'biennial' => ['singular' => 'cada 2 años', 'plural' => 'cada 2 años'],
            'quinquennial' => ['singular' => 'cada 5 años', 'plural' => 'cada 5 años'],
            'decennial' => ['singular' => 'cada 10 años', 'plural' => 'cada 10 años'],
        ];

        $interval = $intervalMap[$this->plan_interval] ?? [
            'singular' => $this->plan_interval,
            'plural' => "{$this->plan_interval}s",
        ];

        $label = $count > 1 ? $interval['plural'] : $interval['singular'];

        return "{$count} {$label}";
    }
}
