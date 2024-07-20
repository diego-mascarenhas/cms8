<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
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
        'status'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    
    public function type()
    {
        return $this->belongsTo(InvoiceType::class);
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status)
        {
            case 1:
                return '<span class="badge rounded-pill bg-label-primary">Print</span>';
            case 2:
                return '<span class="badge rounded-pill bg-label-warning">Printed</span>';
            case 3:
                return '<span class="badge rounded-pill bg-label-danger">Cancelled</span>';
            case 4:
                return '<span class="badge rounded-pill bg-label-info">Credit Note</span>';
            case 5:
                return '<span class="badge rounded-pill bg-label-success">Bonified</span>';
            case 6:
                return '<span class="badge rounded-pill bg-label-success">Bonified (Credit Note)</span>';
            case 7:
                return '<span class="badge rounded-pill bg-label-danger">Error</span>';
            case 8:
                return '<span class="badge rounded-pill bg-label-warning">Issuing</span>';
            default:
                return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
        }
    }

}