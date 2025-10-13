<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
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
    ];

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
            default => 'secondary',
        };

        return '<span class="badge rounded-pill bg-label-'.$color.'">'.$label.'</span>';
    }
}
