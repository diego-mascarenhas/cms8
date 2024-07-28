<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enterprise extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type_id',
        'user_id',
        'assigned_to',
        'referred_by',
        'address',
        'postal_code',
        'locality',
        'province',
        'country',
        'phone',
        'whatsapp',
        'email',
        'website',
        'payment_method_id',
        'invoice_type_id',
        'status',
    ];

    public function scopeClients($query)
    {
        return $query->where('type_id', 1);
    }
    
    public function type()
    {
        return $this->belongsTo(EnterpriseType::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function enterpriseBillingAddress()
    {
        return $this->hasMany(EnterpriseBillingAddress::class)
                ->where('status', 1)
                ->latest()
                ->first();
    }
}
