<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mailbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'name',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'protocol',
        'folder',
    ];

    protected $casts = [
        'port' => 'integer',
        'password' => 'encrypted',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function isPersonal(): bool
    {
        return $this->user_id !== null;
    }

    public function isTeamShared(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Company / shared mailboxes (no owner user).
     */
    public function scopeTeamShared(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * Personal mailboxes for a given user.
     */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $userId);
    }

    /**
     * Team mailboxes the user may read/sync: shared + their own personal.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void
        {
            $q->whereNull('user_id')
                ->orWhere('user_id', $user->id);
        });
    }
}
