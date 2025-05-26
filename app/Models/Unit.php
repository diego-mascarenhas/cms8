<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'type'
    ];

    /**
     * Get the fares for this unit
     */
    public function fares()
    {
        return $this->hasMany(Fare::class);
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