<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'code',
        'address',
        'data',
        'status',
        'is_main',
    ];

    protected $casts = [
        'data' => 'array',
        'status' => 'boolean',
        'is_main' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Ensure the team has a default main branch (code MAIN). Other stores lose is_main.
     */
    public static function ensureMainStoreForTeam(int $teamId): self
    {
        $main = static::withoutGlobalScope('team')->updateOrCreate(
            [
                'team_id' => $teamId,
                'code' => 'MAIN',
            ],
            [
                'name' => 'Principal',
                'address' => null,
                'status' => true,
                'is_main' => true,
            ],
        );

        static::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('id', '!=', $main->id)
            ->update(['is_main' => false]);

        return $main;
    }
}
