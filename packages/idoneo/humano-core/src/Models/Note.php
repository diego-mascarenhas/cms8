<?php

namespace Idoneo\HumanoCore\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'is_private',
        'notable_type',
        'notable_id',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     */
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
     * Get the team that owns the note.
     */
    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    /**
     * Get the user that created the note.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent notable model.
     */
    public function notable()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include public notes or user's private notes.
     */
    public function scopeAccessible($query)
    {
        return $query->where(function ($q)
        {
            $q->where('is_private', false)
                ->orWhere('user_id', auth()->id());
        });
    }

    /**
     * Scope a query to filter by notable type.
     */
    public function scopeForNotable($query, string $type, int $id)
    {
        return $query->where('notable_type', $type)
            ->where('notable_id', $id);
    }

    /**
     * Get the excerpt of the content.
     */
    public function getExcerptAttribute(int $length = 100): string
    {
        return str($this->content)->limit($length);
    }
}
