<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnterpriseTaxStatusType extends Model
{
    use HasFactory;

    protected $table = 'enterprise_tax_status_types';

    public $timestamps = false;

    protected $fillable = ['name'];

    public function label(): string
    {
        $name = trim((string) $this->name);
        if ($name === '')
        {
            return '';
        }

        $translated = __('tax_status.'.$name);

        return $translated === 'tax_status.'.$name ? $name : $translated;
    }

    public function enterpriseBillingAddresses()
    {
        return $this->hasMany(EnterpriseBillingAddress::class, 'fiscal_condition_type_id');
    }
}
