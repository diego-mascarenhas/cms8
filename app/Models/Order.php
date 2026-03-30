<?php

namespace App\Models;

use App\Helpers\LegacyTiendaPedidoEstadoHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'contact_id',
        'team_id',
        'store_id',
        'total_amount',
        'currency_id',
        'payment_status',
        'delivery_status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
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
    }

    /**
     * Get the contact that owns the order.
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the currency that owns the order.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the team that owns the order.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Branch / store this order was placed for (WhatsApp tienda), when known.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Payment method labels for order detail (WhatsApp checkout snapshot or branch defaults).
     *
     * @return list<string>
     */
    public function checkoutPaymentMethodDisplayLabels(): array
    {
        $offered = $this->metadata['checkout_offered'] ?? null;
        if (is_array($offered))
        {
            if (! empty($offered['payment_method_labels']) && is_array($offered['payment_method_labels']))
            {
                return array_values(array_map('strval', $offered['payment_method_labels']));
            }
            if (! empty($offered['payment_methods']) && is_array($offered['payment_methods']))
            {
                $map = Store::checkoutPaymentMethodLabels();
                $out = [];
                foreach ($offered['payment_methods'] as $k)
                {
                    $key = (string) $k;
                    $out[] = (string) ($map[$key] ?? $key);
                }

                return $out;
            }
        }

        $store = $this->store;
        if ($store)
        {
            $map = Store::checkoutPaymentMethodLabels();
            $out = [];
            foreach ($store->enabledCheckoutPaymentMethods() as $k)
            {
                $out[] = (string) ($map[$k] ?? $k);
            }

            return $out;
        }

        return [];
    }

    /**
     * Fulfillment option labels for order detail (snapshot or branch defaults).
     *
     * @return list<string>
     */
    public function checkoutFulfillmentDisplayLabels(): array
    {
        $offered = $this->metadata['checkout_offered'] ?? null;
        if (is_array($offered))
        {
            if (! empty($offered['fulfillment_labels']) && is_array($offered['fulfillment_labels']))
            {
                return array_values(array_map('strval', $offered['fulfillment_labels']));
            }
            if (! empty($offered['fulfillment_types']) && is_array($offered['fulfillment_types']))
            {
                $map = Store::checkoutFulfillmentLabels();
                $out = [];
                foreach ($offered['fulfillment_types'] as $k)
                {
                    $key = (string) $k;
                    $out[] = (string) ($map[$key] ?? $key);
                }

                return $out;
            }
        }

        $store = $this->store;
        if ($store)
        {
            $map = Store::checkoutFulfillmentLabels();
            $out = [];
            foreach ($store->enabledCheckoutFulfillmentTypes() as $k)
            {
                $out[] = (string) ($map[$k] ?? $k);
            }

            return $out;
        }

        return [];
    }

    /**
     * Get payment status badge class
     */
    public function getPaymentStatusBadgeAttribute(): string
    {
        return match ($this->payment_status)
        {
            'paid' => 'bg-label-success',
            'failed' => 'bg-label-danger',
            'refunded' => 'bg-label-warning',
            'cancelled' => 'bg-label-secondary',
            default => 'bg-label-info',
        };
    }

    /**
     * Get delivery status badge class
     */
    public function getDeliveryStatusBadgeAttribute(): string
    {
        return match ($this->delivery_status)
        {
            'delivered' => 'bg-label-success',
            'dispatched' => 'bg-label-primary',
            'out_for_delivery' => 'bg-label-info',
            'cancelled' => 'bg-label-danger',
            default => 'bg-label-warning',
        };
    }

    /**
     * Get payment status label
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status)
        {
            'paid' => __('Pagado'),
            'failed' => __('Fallido'),
            'refunded' => __('Reembolsado'),
            'cancelled' => __('Cancelado'),
            default => __('Pendiente'),
        };
    }

    /**
     * Get delivery status label
     */
    public function getDeliveryStatusLabelAttribute(): string
    {
        return match ($this->delivery_status)
        {
            'delivered' => __('Entregado'),
            'dispatched' => __('Despachado'),
            'out_for_delivery' => __('En camino'),
            'cancelled' => __('Cancelado'),
            default => __('Procesando'),
        };
    }

    /**
     * Legacy `tienda_pedidos.estado` code from import metadata (cms7), if present.
     */
    public function getLegacyEstadoCodeAttribute(): ?int
    {
        $meta = $this->metadata ?? [];
        if (array_key_exists('legacy_estado', $meta) && $meta['legacy_estado'] !== null && $meta['legacy_estado'] !== '')
        {
            return (int) $meta['legacy_estado'];
        }
        if (array_key_exists('legacy_status', $meta) && $meta['legacy_status'] !== null && $meta['legacy_status'] !== '')
        {
            return (int) $meta['legacy_status'];
        }

        return null;
    }

    /**
     * Human-readable legacy status label (cms7 Tienda_model), for audit display.
     */
    public function getLegacyEstadoLabelAttribute(): ?string
    {
        $meta = $this->metadata ?? [];
        if (! empty($meta['legacy_estado_label']))
        {
            return (string) $meta['legacy_estado_label'];
        }

        $code = $this->legacy_estado_code;

        return $code !== null ? LegacyTiendaPedidoEstadoHelper::legacyLabel($code) : null;
    }

    /**
     * Badge class for legacy estado (Vuexy / Tabler label style).
     */
    public function getLegacyEstadoBadgeAttribute(): string
    {
        $code = $this->legacy_estado_code;
        if ($code === null)
        {
            return 'bg-label-secondary';
        }

        return match ($code)
        {
            4, 10 => 'bg-label-danger',
            7 => 'bg-label-success',
            6 => 'bg-label-primary',
            5, 9, 11 => 'bg-label-success',
            1, 2, 3, 8 => 'bg-label-warning',
            default => 'bg-label-secondary',
        };
    }
}
