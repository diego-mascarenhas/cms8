<?php

namespace App\Models;

use Carbon\Carbon;
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
        'currency_id',
        'source_provider',
        'source_reference_id',
        'source_synced_at',
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

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((int) $this->status)
        {
            3 => 'Anulada',
            4 => 'Nota de Crédito',
            5 => 'Bonificada',
            6 => 'Bonificada (Nota de Crédito)',
            7 => 'Error',
            8 => 'Emitiendo',
            9 => 'Borrador',
            default => $this->collectionStatusLabel(),
        };
    }

    public function isOverdue(): bool
    {
        if (in_array((int) $this->status, [3, 4, 5, 6, 7, 9], true))
        {
            return false;
        }

        if ($this->due_date === null || (float) $this->balance <= 0)
        {
            return false;
        }

        return Carbon::parse($this->due_date)->startOfDay()->lt(Carbon::now()->startOfDay());
    }

    public function getStatusBadgeAttribute(): string
    {
        $label = $this->status_label;
        $color = match ($label)
        {
            'Vencida' => 'danger',
            'Pendiente', 'Emitiendo' => 'warning',
            'Cobrada', 'Bonificada', 'Bonificada (Nota de Crédito)' => 'success',
            'Anulada', 'Error' => 'danger',
            'Nota de Crédito' => 'info',
            'Borrador' => 'secondary',
            default => 'secondary',
        };

        return '<span class="badge rounded-pill bg-label-'.$color.'">'.$label.'</span>';
    }

    private function collectionStatusLabel(): string
    {
        if ((float) $this->balance <= 0)
        {
            return 'Cobrada';
        }

        if ($this->isOverdue())
        {
            return 'Vencida';
        }

        return 'Pendiente';
    }

    public function getCurrencyCodeAttribute(): string
    {
        if ($this->relationLoaded('currency'))
        {
            $currency = $this->getRelation('currency');
            if ($currency instanceof Currency)
            {
                return strtoupper((string) $currency->code);
            }
        }

        if ($this->currency_id)
        {
            $code = Currency::query()->whereKey($this->currency_id)->value('code');
            if (filled($code))
            {
                return strtoupper((string) $code);
            }
        }

        return strtoupper((string) config('verifactu.default_currency', 'EUR'));
    }

    /**
     * Convert invoice amount to different currency
     */
    public function convertTo(string $targetCurrency, string $field = 'total_amount'): ?float
    {
        $baseCurrency = $this->currency_code;
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
        $baseCurrency = $this->currency_code;
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
