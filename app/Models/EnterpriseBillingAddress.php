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
        'fiscal_condition_type_id',
        'address',
        'postal_code',
        'city',
        'state',
        'country',
        'status',
    ];

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class);
    }
    
    public function fiscalConditionType()
    {
        return $this->belongsTo(EnterpriseFiscalConditionType::class);
    }
}
