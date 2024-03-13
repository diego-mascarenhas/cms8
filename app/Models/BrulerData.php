<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrulerData extends Model
{
    use HasFactory;

    protected $table = 'bruler_data';

    protected $fillable = ['type', 'data'];

    protected $casts = [
        'data' => 'array',
    ];
}