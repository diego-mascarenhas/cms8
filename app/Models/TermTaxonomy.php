<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * WordPress-equivalent `wp_term_taxonomy` row. The `id` column maps to WordPress'
 * `term_taxonomy_id`.
 */
class TermTaxonomy extends Model
{
    use HasFactory;

    public const TAXONOMY_CATEGORY = 'category';

    public const TAXONOMY_TAG = 'post_tag';

    public $timestamps = false;

    protected $table = 'term_taxonomy';

    protected $fillable = [
        'team_id',
        'term_id',
        'taxonomy',
        'description',
        'parent',
        'count',
    ];

    protected $casts = [
        'parent' => 'integer',
        'count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('term_taxonomy.team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            Post::class,
            'term_relationships',
            'term_taxonomy_id',
            'object_id',
        )->withPivot('term_order');
    }

    public function scopeTaxonomy(Builder $query, string $taxonomy): Builder
    {
        return $query->where('taxonomy', $taxonomy);
    }
}
