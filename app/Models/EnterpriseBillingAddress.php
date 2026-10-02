<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnterpriseBillingAddress extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'enterprise_billing_addresses';

    protected $fillable = [
        'enterprise_id',
        'name',
        'identification_number',
        'tax_status_type_id',
        'address',
        'postal_code',
        'locality',
        'province',
        'country',
        'status',
    ];

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function taxStatusType()
    {
        return $this->belongsTo(EnterpriseTaxStatusType::class, 'tax_status_type_id');
    }

    /**
     * Country name for the billing row. Uses the address country, then a fallback code such as the Stripe customer country.
     */
    public function countryLabel(?string $fallbackCode = null): string
    {
        $raw = trim((string) $this->country);
        if ($raw === '')
        {
            $raw = trim((string) $fallbackCode);
        }

        if ($raw === '')
        {
            return '';
        }

        $match = Country::query()->where('code', strtolower($raw))->value('name');

        return is_string($match) && $match !== '' ? $match : $raw;
    }

    // Deprecated: Use taxStatusType() instead
    public function fiscalConditionType()
    {
        return $this->taxStatusType();
    }
}
