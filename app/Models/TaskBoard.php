<?php

namespace App\Models;

use App\Support\TeamTaskBoardResolver;
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

    public static function getDefaultBoard(): self
    {
        $teamId = auth()->user()?->currentTeam?->id;
        if ($teamId)
        {
            $boardId = TeamTaskBoardResolver::resolveBoardId($teamId);

            return self::withoutGlobalScopes()->findOrFail($boardId);
        }

        $defaultBoard = self::where('is_default', true)->first();
        if ($defaultBoard)
        {
            return $defaultBoard;
        }

        throw new \RuntimeException('No default task board available without team context.');
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
