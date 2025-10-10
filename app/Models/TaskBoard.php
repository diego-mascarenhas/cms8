<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskBoard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'name',
        'description',
        'is_default',
        'order',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::created(function ($board)
        {
            if ($board->is_default)
            {
                // Set all other boards as non-default
                self::where('id', '!=', $board->id)
                    ->where('team_id', $board->team_id)
                    ->update(['is_default' => false]);
            }
        });

        static::updated(function ($board)
        {
            if ($board->is_default)
            {
                // Set all other boards as non-default
                self::where('id', '!=', $board->id)
                    ->where('team_id', $board->team_id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'board_id');
    }

    public function project()
    {
        return $this->hasOne(Project::class, 'board_id');
    }

    public static function getDefaultBoard()
    {
        $defaultBoard = self::where('is_default', true)->first();

        if (! $defaultBoard)
        {
            // Create a default board if none exists
            $teamId = auth()->user()->currentTeam->id;
            $defaultBoard = self::create([
                'team_id' => $teamId,
                'name' => 'Default',
                'description' => 'Default board',
                'is_default' => true,
                'order' => 0,
            ]);
        }

        return $defaultBoard;
    }

    public static function getOptions()
    {
        return self::orderBy('order')->get()->map(function ($board)
        {
            return [
                'id' => $board->id,
                'name' => $board->name,
            ];
        });
    }

    public function getTranslatedNameAttribute()
    {
        return __($this->name);
    }
}
