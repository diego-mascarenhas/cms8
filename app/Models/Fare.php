<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fare extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit_id',
        'glosary_id',
        'block_id'
    ];

    /**
     * Get the unit that belongs to the fare
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the block that belongs to the fare
     */
    public function block()
    {
        return $this->belongsTo(FareBlock::class, 'block_id');
    }

    /**
     * Get the customer fares for this fare
     */
    public function customerFares()
    {
        return $this->hasMany(CustomerFare::class);
    }
} 