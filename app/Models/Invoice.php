<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function stripeInvoiceSync(): HasOne
    {
        return $this->hasOne(InvoiceSync::class, 'external_id', 'source_reference_id')
            ->where('invoice_syncs.provider', 'stripe');
    }

    public function fiscalExports(): HasMany
    {
        return $this->hasMany(FiscalExport::class);
    }

    public function fiscalExport(string $platform): ?FiscalExport
    {
        return $this->fiscalExports()->where('platform', $platform)->first();
    }

    public function stripeHostedInvoiceUrl(): ?string
    {
        $sync = $this->relationLoaded('stripeInvoiceSync')
            ? $this->stripeInvoiceSync
            : $this->stripeInvoiceSync()->first();

        if (! $sync instanceof InvoiceSync)
        {
            return null;
        }

        $url = trim((string) ($sync->hosted_invoice_url ?? ''));

        if ($url !== '')
        {
            return $url;
        }

        $payloadUrl = trim((string) data_get($sync->raw_payload, 'hosted_invoice_url', ''));
        if ($payloadUrl !== '')
        {
            return $payloadUrl;
        }

        // Stripe credit notes only expose a PDF — reuse it for the print action.
        if ($this->isCreditNote())
        {
            return $this->stripeInvoicePdfUrl();
        }

        return null;
    }

    public function stripeInvoicePdfUrl(): ?string
    {
        $sync = $this->relationLoaded('stripeInvoiceSync')
            ? $this->stripeInvoiceSync
            : $this->stripeInvoiceSync()->first();

        if (! $sync instanceof InvoiceSync)
        {
            return null;
        }

        $url = trim((string) ($sync->invoice_pdf ?? ''));

        if ($url !== '')
        {
            return $url;
        }

        foreach (['invoice_pdf', 'pdf', 'pdf_url'] as $key)
        {
            $payloadUrl = trim((string) data_get($sync->raw_payload, $key, ''));
            if ($payloadUrl !== '')
            {
                return $payloadUrl;
            }
        }

        return null;
    }

    public function stripeDashboardUrl(): ?string
    {
        if (strtolower((string) $this->source_provider) !== 'stripe')
        {
            return null;
        }

        $externalId = trim((string) $this->source_reference_id);
        if ($externalId === '')
        {
            return null;
        }

        $resource = match (true)
        {
            str_starts_with($externalId, 'in_') => 'invoices',
            str_starts_with($externalId, 'cn_') => 'credit_notes',
            default => null,
        };

        if ($resource === null)
        {
            return null;
        }

        $sync = $this->relationLoaded('stripeInvoiceSync')
            ? $this->stripeInvoiceSync
            : $this->stripeInvoiceSync()->first();

        $livemode = $sync instanceof InvoiceSync
            ? data_get($sync->raw_payload, 'livemode')
            : null;

        $base = $livemode === false
            ? 'https://dashboard.stripe.com/test/'
            : 'https://dashboard.stripe.com/';

        return $base.$resource.'/'.$externalId;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((int) $this->status)
        {
            3 => 'Anulada',
            4 => 'Nota de Crédito',
            5 => 'Bonificada',
            6 => 'Bonificada (Nota de Crédito)',
            7 => 'Incobrable',
            8 => 'Emitiendo',
            9 => 'Borrador',
            default => $this->collectionStatusLabel(),
        };
    }

    public function isCreditNote(): bool
    {
        if (in_array((int) $this->status, [4, 6], true))
        {
            return true;
        }

        if ((int) $this->type_id === 2)
        {
            return true;
        }

        return str_starts_with((string) $this->source_reference_id, 'cn_');
    }

    /**
     * Provider-facing document number (e.g. Stripe CN number), kept on invoice_syncs.
     */
    public function providerNumber(): ?string
    {
        $sync = $this->relationLoaded('stripeInvoiceSync')
            ? $this->stripeInvoiceSync
            : $this->stripeInvoiceSync()->first();

        if ($sync instanceof InvoiceSync)
        {
            $fromSync = trim((string) ($sync->number ?? ''));
            if ($fromSync !== '')
            {
                return $fromSync;
            }

            $fromPayload = trim((string) data_get($sync->raw_payload, 'number', ''));
            if ($fromPayload !== '')
            {
                return $fromPayload;
            }
        }

        return null;
    }

    /**
     * Stripe invoice id this credit note was issued against (from invoice_syncs.raw_payload).
     */
    public function stripeOriginalInvoiceExternalId(): ?string
    {
        if (! $this->isCreditNote())
        {
            return null;
        }

        $sync = $this->relationLoaded('stripeInvoiceSync')
            ? $this->stripeInvoiceSync
            : $this->stripeInvoiceSync()->first();

        if (! $sync instanceof InvoiceSync)
        {
            return null;
        }

        $invoiceField = data_get($sync->raw_payload, 'invoice');
        if (is_array($invoiceField))
        {
            $invoiceField = data_get($invoiceField, 'id');
        }

        $externalId = trim((string) $invoiceField);

        return str_starts_with($externalId, 'in_') ? $externalId : null;
    }

    /**
     * Local invoice document that this credit note rectifies.
     */
    public function originalInvoice(): ?self
    {
        $externalId = $this->stripeOriginalInvoiceExternalId();
        if ($externalId === null)
        {
            return null;
        }

        return static::withoutGlobalScopes()
            ->where('team_id', $this->team_id)
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $externalId)
            ->first();
    }

    /**
     * Local credit-note documents issued against this Stripe invoice.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public function relatedCreditNotes()
    {
        $externalId = trim((string) $this->source_reference_id);
        if ($externalId === '' || ! str_starts_with($externalId, 'in_'))
        {
            return collect();
        }

        $creditNoteExternalIds = InvoiceSync::query()
            ->where('team_id', $this->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', 'like', 'cn_%')
            ->where('raw_payload->invoice', $externalId)
            ->pluck('external_id');

        if ($creditNoteExternalIds->isEmpty())
        {
            return collect();
        }

        return static::withoutGlobalScopes()
            ->where('team_id', $this->team_id)
            ->whereIn('source_reference_id', $creditNoteExternalIds)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Most recent related credit note, if any.
     */
    public function existingCreditNote(): ?self
    {
        $related = $this->relatedCreditNotes();

        return $related->first();
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
            'Anulada' => 'danger',
            'Incobrable' => 'warning',
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
