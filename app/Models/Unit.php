<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Get the fares for this unit
     */
    public function fares()
    {
        return $this->belongsToMany(Fare::class, 'fare_unit');
    }

    /**
     * Common unit types
     */
    public static function getTypes()
    {
        return [
            'min' => 'Minuto',
            'pal' => 'Palabra',
            'pag' => 'Página',
            'rollo' => 'Rollo',
            'hour' => 'Hora'
        ];
    }
} 