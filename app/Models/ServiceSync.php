<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceSync extends Model
{
    use HasFactory;

    protected $table = 'service_syncs';

    protected $fillable = [
        'stripe_id',
        'provider',
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
        'provider' => 'stripe',
        'type' => 'sell',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Link flow must only resolve a sync row in the current team (same as the list).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();
        $user = auth()->user();
        if (! $user?->currentTeam)
        {
            abort(404);
        }

        return static::query()
            ->where('team_id', $user->currentTeam->id)
            ->where($field, $value)
            ->firstOrFail();
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
     * Services in Humano linked to this sync row (services.subscription_id).
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

    /**
     * Name shown on the client page. Stripe price nicknames were bulk-renamed
     * (for example "Actualizado 12/2025"); the subscription description keeps the real label.
     */
    public function clientFacingName(): string
    {
        $description = trim((string) data_get($this->raw_payload, 'description'));
        if ($description !== '')
        {
            return $description;
        }

        $plan = trim((string) $this->plan_name);
        if ($plan !== '')
        {
            return $plan;
        }

        return (string) $this->stripe_id;
    }

    /**
     * Hosting plans open the hosting account. Metadata category wins; otherwise the client-facing name starts with Hosting.
     */
    public function isClientHosting(): bool
    {
        $category = strtolower(trim((string) data_get($this->data, 'category', data_get($this->data, 'type'))));

        if ($category === 'hosting')
        {
            return true;
        }

        if ($category !== '')
        {
            return false;
        }

        return (bool) preg_match('/^(hosting|plan de hosting)\b/i', $this->clientFacingName());
    }

    /**
     * Domain used to find the hosting account. Metadata is preferred; the name is the fallback when metadata is empty.
     */
    public function hostingDomainCandidate(): ?string
    {
        $fromData = strtolower(trim((string) data_get($this->data, 'domain')));
        if ($fromData !== '')
        {
            return $fromData;
        }

        if (preg_match('/\b((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,})\b/i', $this->clientFacingName(), $matches) === 1)
        {
            return strtolower($matches[1]);
        }

        return null;
    }

    /**
     * Stripe coupon on the subscription, or the percent stored on the linked service.
     */
    public function clientDiscountLabel(?Service $service = null): string
    {
        $fromStripe = $this->stripeDiscountLabel();
        if ($fromStripe !== '')
        {
            return $fromStripe;
        }

        $percent = (float) ($service?->discount ?? 0);
        if ($percent > 0)
        {
            return $this->formatDiscountPercent($percent);
        }

        return '';
    }

    public function clientPaymentMethodLabel(): string
    {
        $brand = trim((string) data_get($this->raw_payload, 'default_payment_method.card.brand'));
        $last4 = trim((string) data_get($this->raw_payload, 'default_payment_method.card.last4'));
        if ($brand !== '' && $last4 !== '')
        {
            return ucfirst($brand).' ···· '.$last4;
        }

        return match (strtolower(trim((string) $this->collection_method)))
        {
            'charge_automatically' => 'Cargo automático',
            'send_invoice' => 'Factura',
            default => '',
        };
    }

    public function clientFrequencyLabel(): string
    {
        $count = max(1, (int) ($this->plan_interval_count ?? 1));

        return match ($this->plan_interval)
        {
            'day' => $count === 1 ? 'Diaria' : $count.' días',
            'week' => $count === 1 ? 'Semanal' : $count.' semanas',
            'month' => $count === 1 ? 'Mensual' : $count.' meses',
            'quarter' => 'Trimestral',
            'semester' => 'Semestral',
            'year' => $count === 1 ? 'Anual' : $count.' años',
            default => (string) ($this->billing_frequency ?: ''),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        $raw = strtolower(trim((string) $this->status));
        if ($raw === '')
        {
            return '';
        }

        $color = match ($raw)
        {
            'active' => 'success',
            'trialing', 'not_started' => 'info',
            'past_due', 'unpaid' => 'warning',
            'incomplete' => 'dark',
            default => 'secondary',
        };

        $key = 'stripe_subscription.status.'.$raw;
        $label = $raw === 'not_started' ? 'Programada' : __($key);
        if ($label === $key)
        {
            $label = str_replace('_', ' ', $raw);
        }

        return '<span class="badge rounded-pill bg-label-'.$color.'">'.e($label).'</span>';
    }

    private function stripeDiscountLabel(): string
    {
        $discounts = data_get($this->raw_payload, 'discounts');
        if (! is_array($discounts) || $discounts === [])
        {
            $single = data_get($this->raw_payload, 'discount');
            $discounts = is_array($single) ? [$single] : [];
        }

        $labels = [];
        foreach ($discounts as $discount)
        {
            if (! is_array($discount))
            {
                continue;
            }

            $coupon = data_get($discount, 'coupon');
            if (! is_array($coupon))
            {
                $coupon = data_get($discount, 'source.coupon');
            }
            if (! is_array($coupon))
            {
                continue;
            }

            $percent = data_get($coupon, 'percent_off');
            if (is_numeric($percent) && (float) $percent > 0)
            {
                $labels[] = $this->formatDiscountPercent((float) $percent);

                continue;
            }

            $amountOff = data_get($coupon, 'amount_off');
            if (is_numeric($amountOff) && (int) $amountOff > 0)
            {
                $currency = strtoupper((string) (data_get($coupon, 'currency') ?: $this->price_currency));
                $labels[] = number_format(((int) $amountOff) / 100, 2).($currency !== '' ? ' '.$currency : '');
            }
        }

        return implode(', ', $labels);
    }

    private function formatDiscountPercent(float $percent): string
    {
        $formatted = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return $formatted.'%';
    }
}
