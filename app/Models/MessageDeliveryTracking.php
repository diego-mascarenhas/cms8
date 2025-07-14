<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryTracking extends Model
{
    use HasFactory;

    protected $table = 'message_delivery_tracking';

    protected $fillable = [
        'message_delivery_id',
        'event',
        'ip_address',
        'user_agent',
    ];

    public function delivery()
    {
        return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
    }
}
