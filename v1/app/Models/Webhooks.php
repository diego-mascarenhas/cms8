<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhooks extends Model
{
    protected $table = 'webhooks';

    protected $casts = [
        'data' => 'array',
    ];

    protected $fillable = ['name', 'email', 'data'];
}
