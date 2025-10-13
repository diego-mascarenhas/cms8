<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryStat extends Model
{
    use HasFactory;

    protected $table = 'message_delivery_stats';

    protected $fillable = [
        'message_id',
        'subscribers',
        'remaining',
        'failed',
        'sent',
        'rejected',
        'delivered',
        'opened',
        'unsubscribed',
        'clicks',
        'unique_opens',
        'ratio',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
