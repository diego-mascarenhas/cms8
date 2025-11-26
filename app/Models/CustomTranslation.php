<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CustomTranslation extends Model
{
    protected $fillable = [
        'team_id',
        'key',
        'value',
        'locale',
        'group',
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

        // Clear cache on model changes
        static::saved(fn($translation) => Cache::forget("custom_translations_team_{$translation->team_id}"));
        static::deleted(fn($translation) => Cache::forget("custom_translations_team_{$translation->team_id}"));
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    // Scopes for easier querying
    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    public function scopeByLocale($query, $locale)
    {
        return $query->where('locale', $locale);
    }
}
