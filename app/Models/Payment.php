<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'enterprise_id',
        'transaction_type',
        'date',
        'invoice_id',
        'account_id',
        'type_id',
        'amount',
        'remarks',
        'status'
    ];

    protected $appends = ['transaction_type_label'];

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // public function account()
    // {
    //     return $this->belongsTo(PaymentAccount::class);
    // }

    public function type()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function getTransactionTypeLabelAttribute()
    {
        switch ($this->transaction_type)
        {
            case 'I':
                return 'Income';
            case 'E':
                return 'Expense';
            default:
                return 'Unknown';
        }
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status)
        {
            case 0:
                return '<span class="badge rounded-pill bg-label-secondary">Deleted</span>';
            case 1:
                return '<span class="badge rounded-pill bg-label-primary">In Process</span>';
            case 2:
                return '<span class="badge rounded-pill bg-label-success">Approved</span>';
            case 3:
                return '<span class="badge rounded-pill bg-label-warning">Pending</span>';
            case 4:
                return '<span class="badge rounded-pill bg-label-danger">Rejected</span>';
            case 5:
                return '<span class="badge rounded-pill bg-label-info">Refunded</span>';
            case 6:
                return '<span class="badge rounded-pill bg-label-danger">Cancelled</span>';
            case 7:
                return '<span class="badge rounded-pill bg-label-warning">In Mediation</span>';
            case 8:
                return '<span class="badge rounded-pill bg-label-danger">Charged Back</span>';
            case 9:
                return '<span class="badge rounded-pill bg-label-warning">Insufficient Funds</span>';
            case 10:
                return '<span class="badge rounded-pill bg-label-danger">Account Closed</span>';
            case 11:
                return '<span class="badge rounded-pill bg-label-secondary">Non-existent Account</span>';
            case 12:
                return '<span class="badge rounded-pill bg-label-secondary">Service Cancelled</span>';
            case 13:
                return '<span class="badge rounded-pill bg-label-secondary">Unspecified</span>';
            case 14:
                return '<span class="badge rounded-pill bg-label-secondary">Expired</span>';
            case 15:
                return '<span class="badge rounded-pill bg-label-danger">Failed</span>';
            case 20:
                return '<span class="badge rounded-pill bg-label-info">Different Currency</span>';
            default:
                return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
        }
    }

}
