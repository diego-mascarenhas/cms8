<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Software extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'software';

    protected $fillable = [
        'name',
        'team_id',
        'type_id',
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
     * Get the type that owns the software.
     */
    public function type()
    {
        return $this->belongsTo(SoftwareType::class, 'type_id');
    }

    /**
     * Get the team that owns the software.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
} 