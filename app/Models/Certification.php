<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Certification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'certification',
        'language',
        'team_id'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    /**
     * Get the team that owns the certification
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Set the language attribute to lowercase
     */
    public function setLanguageAttribute($value)
    {
        $this->attributes['language'] = strtolower($value);
    }
}
