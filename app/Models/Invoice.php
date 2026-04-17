<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'enterprise_id',
        'billing_id',
        'type_id',
        'operation',
        'number',
        'date',
        'due_date',
        'gross_amount',
        'discount',
        'total_amount',
        'balance',
        'status',
        'currency',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where(
                    $builder->qualifyColumn('team_id'),
                    auth()->user()->currentTeam->id,
                );
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class, 'enterprise_id');
    }

    public function type()
    {
        return $this->belongsTo(InvoiceType::class);
    }

    public function billingAddress()
    {
        return $this->belongsTo(EnterpriseBillingAddress::class, 'billing_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status)
        {
            1 => 'Imprimir',
            2 => 'Impresa',
            3 => 'Anulada',
            4 => 'Nota de Crédito',
            5 => 'Bonificada',
            6 => 'Bonificada (Nota de Crédito)',
            7 => 'Error',
            8 => 'Emitiendo',
            9 => 'Borrador',
            default => 'Desconocido',
        };
    }

    public function getStatusBadgeAttribute()
    {
        $label = $this->status_label;
        $color = match ($this->status)
        {
            1 => 'primary',
            2 => 'warning',
            3 => 'danger',
            4 => 'info',
            5 => 'success',
            6 => 'success',
            7 => 'danger',
            8 => 'warning',
            9 => 'secondary',
            default => 'secondary',
        };

        return '<span class="badge rounded-pill bg-label-'.$color.'">'.$label.'</span>';
    }

    /**
     * Convert invoice amount to different currency
     */
    public function convertTo(string $targetCurrency, string $field = 'total_amount'): ?float
    {
        $baseCurrency = $this->currency ?? 'USD';
        $amount = $this->$field ?? 0;

        if ($baseCurrency === $targetCurrency)
        {
            return $amount;
        }

        return ExchangeRate::convert($amount, $baseCurrency, $targetCurrency);
    }

    /**
     * Get invoice amount in multiple currencies
     */
    public function getMultiCurrencyAttribute()
    {
        $baseCurrency = $this->currency ?? 'USD';
        $currencies = ['USD', 'EUR', 'ARS'];
        $amounts = [];

        foreach ($currencies as $currency)
        {
            if ($currency === $baseCurrency)
            {
                $amounts[$currency] = $this->total_amount;
            } else
            {
                $amounts[$currency] = $this->convertTo($currency, 'total_amount');
            }
        }

        return $amounts;
    }
}
