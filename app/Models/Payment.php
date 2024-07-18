<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'transaction_type',
        'date',
        'invoice_id',
        'account_id',
        'type_id',
        'amount',
        'remarks',
        'status'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // public function account()
    // {
    //     return $this->belongsTo(PaymentAccount::class);
    // }

    // public function paymentType()
    // {
    //     return $this->belongsTo(PaymentType::class);
    // }

    // public function status()
    // {
    //     return $this->belongsTo(PaymentStatus::class, 'status');
    // }
}
