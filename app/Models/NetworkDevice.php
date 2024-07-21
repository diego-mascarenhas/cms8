<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'device_type',
    ];

    public function hostConnections()
    {
        return $this->hasMany(Host::class, 'private_connection_id');
    }

    public function publicConnections()
    {
        return $this->hasMany(Host::class, 'public_connection_id');
    }
}
