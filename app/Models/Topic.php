<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Topic extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'topics';

    protected $fillable = [
        'name',
        'team_id',
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
     * Get the team that owns the topic.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the contacts that use this topic.
     */
    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_topics')
                    ->withPivot('proficiency_level', 'notes')
                    ->withTimestamps();
    }

    /**
     * Get topic options for dropdowns and autocomplete.
     */
    public static function getOptions()
    {
        return self::get()->map(function ($data) {
            return [
                'id' => $data->id,
                'name' => $data->name,
                'text' => $data->name,
            ];
        });
    }
} 