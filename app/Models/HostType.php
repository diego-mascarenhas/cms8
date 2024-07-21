<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function hosts()
    {
        return $this->hasMany(Host::class);
    }
}
