<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'enterprise_id',
        'transaction_type',
        'date',
        'invoice_id',
        'account_id',
        'type_id',
        'amount',
        'remarks',
        'status',
        'source_provider',
        'source_reference_id',
        'source_synced_at',
    ];

    protected $casts = [
        'transaction_type' => TransactionType::class,
        'date' => 'date',
        'source_synced_at' => 'datetime',
    ];

    protected $appends = ['transaction_type_label'];

    protected static function booted()
    {
        static::addGlobalScope('team', function ($builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function scopeApprovedStatus($query)
    {
        return $query->where('status', 2);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getCurrencyCodeAttribute(): string
    {
        if ($this->relationLoaded('account'))
        {
            $currency = $this->account?->currency;
            if ($currency instanceof Currency)
            {
                return strtoupper((string) $currency->code);
            }
        }

        if ($this->account_id)
        {
            $code = PaymentAccount::withoutGlobalScopes()
                ->with('currency')
                ->whereKey($this->account_id)
                ->first()
                ?->currency
                ?->code;

            if (filled($code))
            {
                return strtoupper((string) $code);
            }
        }

        if ($this->relationLoaded('invoice') && $this->invoice instanceof Invoice)
        {
            return $this->invoice->currency_code;
        }

        if ($this->invoice_id)
        {
            $code = Invoice::query()->whereKey($this->invoice_id)->value('currency_id');
            if ($code !== null)
            {
                $iso = Currency::query()->whereKey($code)->value('code');
                if (filled($iso))
                {
                    return strtoupper((string) $iso);
                }
            }
        }

        return strtoupper((string) config('verifactu.default_currency', 'EUR'));
    }

    public function account()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function type()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function getTransactionTypeLabelAttribute()
    {
        return $this->transaction_type?->label() ?? __('Unknown');
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status)
        {
            case 0:
                return '<span class="badge rounded-pill bg-label-secondary">'.__('Deleted').'</span>';
            case 1:
                return '<span class="badge rounded-pill bg-label-primary">'.__('In Process').'</span>';
            case 2:
                return '<span class="badge rounded-pill bg-label-success">'.__('Approved').'</span>';
            case 3:
                return '<span class="badge rounded-pill bg-label-warning">'.__('Pending').'</span>';
            case 4:
                return '<span class="badge rounded-pill bg-label-danger">'.__('Rejected').'</span>';
            case 5:
                return '<span class="badge rounded-pill bg-label-info">'.__('Refunded').'</span>';
            case 6:
                return '<span class="badge rounded-pill bg-label-danger">'.__('Cancelled').'</span>';
            case 7:
                return '<span class="badge rounded-pill bg-label-warning">'.__('In Mediation').'</span>';
            case 8:
                return '<span class="badge rounded-pill bg-label-danger">'.__('Charged Back').'</span>';
            case 9:
                return '<span class="badge rounded-pill bg-label-warning">'.__('Insufficient Funds').'</span>';
            case 10:
                return '<span class="badge rounded-pill bg-label-danger">'.__('Account Closed').'</span>';
            case 11:
                return '<span class="badge rounded-pill bg-label-secondary">'.__('Non-existent Account').'</span>';
            case 12:
                return '<span class="badge rounded-pill bg-label-secondary">'.__('Service Cancelled').'</span>';
            case 13:
                return '<span class="badge rounded-pill bg-label-secondary">'.__('Unspecified').'</span>';
            case 14:
                return '<span class="badge rounded-pill bg-label-secondary">'.__('Expired').'</span>';
            case 15:
                return '<span class="badge rounded-pill bg-label-danger">'.__('Failed').'</span>';
            case 20:
                return '<span class="badge rounded-pill bg-label-info">'.__('Different Currency').'</span>';
            default:
                return '<span class="badge rounded-pill bg-label-secondary">'.__('Unknown').'</span>';
        }
    }
}
