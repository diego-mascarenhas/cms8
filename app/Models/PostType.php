<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Humano registry of content types (the DB equivalent of WordPress' register_post_type()).
 */
class PostType extends Model
{
    use HasFactory;

    protected $table = 'post_types';

    protected $fillable = [
        'team_id',
        'name',
        'label',
        'label_singular',
        'icon',
        'supports',
        'hierarchical',
        'has_archive',
        'public',
        'taxonomies',
        'data',
        'menu_order',
    ];

    protected $casts = [
        'supports' => 'array',
        'taxonomies' => 'array',
        'data' => 'array',
        'hierarchical' => 'boolean',
        'has_archive' => 'boolean',
        'public' => 'boolean',
        'menu_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('post_types.team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function (self $postType)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $postType->team_id = $postType->team_id ?? auth()->user()->currentTeam->id;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'post_type', 'name');
    }

    /**
     * Whether this post type declares support for a given feature (title, editor, etc.).
     */
    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports ?? [], true);
    }
}
