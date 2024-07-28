<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnterpriseFiscalConditionType extends Model
{
    use HasFactory;

    protected $table = 'enterprise_fiscal_condition_types';

    public $timestamps = false;

    protected $fillable = ['name'];

    public function enterpriseBillingAddresses()
    {
        return $this->hasMany(EnterpriseBillingAddress::class);
    }
}