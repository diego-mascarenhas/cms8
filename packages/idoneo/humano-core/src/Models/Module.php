<?php

namespace Idoneo\HumanoCore\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'icon',
        'description',
        'is_core',
        'status',
    ];

    protected $casts = [
        'is_core' => 'boolean',
    ];

    /**
     * Get the teams that have this module enabled.
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('settings', 'status')
            ->withTimestamps();
    }

    /**
     * Check if the module is active for a specific team
     */
    public function isActiveForTeam($teamId)
    {
        return $this->teams()
            ->where('team_id', $teamId)
            ->where('module_team.status', 1)
            ->exists();
    }

    /**
     * Get categories associated with this module
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
