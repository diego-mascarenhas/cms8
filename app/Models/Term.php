<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WordPress-equivalent `wp_terms` row. The `id` column maps to WordPress' `term_id`.
 */
class Term extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'terms';

    protected $fillable = [
        'team_id',
        'wp_id',
        'name',
        'slug',
        'term_group',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('terms.team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function (self $term)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $term->team_id = $term->team_id ?? auth()->user()->currentTeam->id;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function taxonomies(): HasMany
    {
        return $this->hasMany(TermTaxonomy::class, 'term_id');
    }
}
