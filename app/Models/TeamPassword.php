<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class TeamPassword extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'enterprise_id',
        'name',
        'username',
        'password_encrypted',
        'url',
        'notes',
        'created_by',
        'updated_by',
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

        static::creating(function (self $password)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $password->team_id = $password->team_id ?? auth()->user()->currentTeam->id;
                $password->created_by = $password->created_by ?? auth()->id();
                $password->updated_by = $password->updated_by ?? auth()->id();
            }
        });

        static::updating(function (self $password)
        {
            if (auth()->check())
            {
                $password->updated_by = auth()->id();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(TeamPasswordShare::class)->latest('id');
    }

    public function getPasswordPlainText(): ?string
    {
        if (! filled($this->password_encrypted))
        {
            return null;
        }

        return Crypt::decryptString($this->password_encrypted);
    }
}
