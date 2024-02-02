<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserServiceTrans extends Model
{
    protected $table = 'users_services_trans';

    protected $fillable = [
        'user_id', 'service_id', 'status', 'transaction_date', 'amount', 'notes'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
