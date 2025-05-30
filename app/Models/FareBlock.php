<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FareBlock extends Model
{
    use HasFactory;

    protected $table = 'fares_blocks';

    protected $fillable = [
        'name'
    ];

    /**
     * Get the fares for this block
     */
    public function fares()
    {
        return $this->hasMany(Fare::class, 'block_id');
    }
} 