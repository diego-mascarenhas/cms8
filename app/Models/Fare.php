<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fare extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'glosary_id',
        'type_id'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Get the units that belong to the fare
     */
    public function units()
    {
        return $this->belongsToMany(Unit::class, 'fare_unit');
    }

    /**
     * Get the type that belongs to the fare
     */
    public function type()
    {
        return $this->belongsTo(FareType::class, 'type_id');
    }

    /**
     * Get the user fares for this fare
     */
    public function userFares()
    {
        return $this->hasMany(UserFare::class);
    }
} 