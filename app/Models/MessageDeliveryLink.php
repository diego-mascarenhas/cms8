<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryLink extends Model
{
    use HasFactory;

    public $timestamps = true; // Now has both created_at and updated_at

    protected $fillable = [
        'message_delivery_id',
        'link',
        'click_count',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'click_count' => 'integer',
    ];

    public function messageDelivery()
    {
        return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
    }
}
