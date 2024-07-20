<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function communications()
    {
        return $this->hasMany(Communication::class);
    }
}