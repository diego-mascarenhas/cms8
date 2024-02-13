<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SysRequest extends Model
{
    protected $table = 'sys_requests';

    protected $casts = [
        'data' => 'array',
    ];

    protected $fillable = ['data'];
}