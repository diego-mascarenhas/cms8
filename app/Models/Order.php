<?php

namespace App\Models;

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
}
