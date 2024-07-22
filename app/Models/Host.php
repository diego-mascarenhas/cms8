<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Host extends Model
{
    use HasFactory;

    protected $table = 'hosts';

    protected $fillable = [
        'name',
        'type_id',
        'user',
        'password',
        'private_ip',
        'private_connection_id',
        'public_ip',
        'public_connection_id',
        'data',
        'power_state',
        'connection_state',
    ];

    public function setUserAttribute($value)
    {
        $this->attributes['user'] = Crypt::encryptString($value);
    }

    public function getUserAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function type()
    {
        return $this->belongsTo(HostType::class);
    }

    public function hostConnection()
    {
        return $this->belongsTo(NetworkDevice::class, 'private_connection_id');
    }

    public function publicConnection()
    {
        return $this->belongsTo(NetworkDevice::class, 'public_connection_id');
    }
}
