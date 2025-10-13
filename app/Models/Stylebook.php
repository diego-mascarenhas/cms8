<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stylebook extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'file',
        'language',
        'date',
        'team_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'deleted_at' => 'datetime',
    ];

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
     * Get the team that owns the stylebook
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the language associated with the stylebook
     */
    public function languageRelation()
    {
        return $this->belongsTo(Language::class, 'language', 'code');
    }

    /**
     * Set the language attribute to lowercase
     */
    public function setLanguageAttribute($value)
    {
        $this->attributes['language'] = strtolower($value);
    }
}
