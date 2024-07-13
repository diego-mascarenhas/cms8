<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Host extends Model
{
    use HasFactory;

    protected $fillable = ['host', 'name', 'power_state', 'cpu_count', 'memory_size_MiB', 'connection_state'];
}
