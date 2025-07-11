<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fare extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'team_id',
        'glosary_id',
        'type_id',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

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
     * Get the team that owns the fare
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user fares for this fare
     */
    public function userFares()
    {
        return $this->hasMany(UserFare::class);
    }
}
