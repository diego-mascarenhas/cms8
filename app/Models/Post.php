<?php

namespace App\Models;

use App\Jobs\PushPostToWordPressJob;
use App\Services\Cms\WordPressContentSyncService;
use App\Support\TeamPostsApiCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * WordPress-equivalent post entity. A single table backs every content type
 * (page, post, attachment, custom) discriminated by {@see $post_type}.
 */
class Post extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PUBLISH = 'publish';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FUTURE = 'future';

    public const STATUS_PRIVATE = 'private';

    public const STATUS_TRASH = 'trash';

    protected $table = 'posts';

    protected $fillable = [
        'team_id',
        'wp_id',
        'wp_modified_gmt',
        'synced_at',
        'post_author',
        'post_date',
        'post_date_gmt',
        'post_content',
        'post_title',
        'post_excerpt',
        'post_status',
        'comment_status',
        'ping_status',
        'post_password',
        'post_name',
        'post_parent',
        'guid',
        'menu_order',
        'post_type',
        'post_mime_type',
        'comment_count',
        'post_modified',
        'post_modified_gmt',
    ];

    protected $casts = [
        'post_date' => 'datetime',
        'post_date_gmt' => 'datetime',
        'post_modified' => 'datetime',
        'post_modified_gmt' => 'datetime',
        'wp_modified_gmt' => 'datetime',
        'synced_at' => 'datetime',
        'menu_order' => 'integer',
        'post_parent' => 'integer',
        'comment_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('posts.team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function (self $post)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $post->team_id = $post->team_id ?? auth()->user()->currentTeam->id;
                $post->post_author = $post->post_author ?? auth()->id();
            }

            $now = now();
            $post->post_date = $post->post_date ?? $now;
            $post->post_date_gmt = $post->post_date_gmt ?? $now->copy()->utc();
            $post->post_modified = $post->post_modified ?? $now;
            $post->post_modified_gmt = $post->post_modified_gmt ?? $now->copy()->utc();
        });

        static::updating(function (self $post)
        {
            $now = now();
            $post->post_modified = $now;
            $post->post_modified_gmt = $now->copy()->utc();
        });

        $bump = function (self $post)
        {
            if ($post->team_id)
            {
                TeamPostsApiCache::bumpTeam((int) $post->team_id);
            }
        };

        static::saved($bump);
        static::deleted($bump);

        static::saved(function (self $post)
        {
            if (WordPressContentSyncService::isPushSuppressed())
            {
                return;
            }

            if (! in_array($post->post_type, WordPressContentSyncService::SYNCED_TYPES, true))
            {
                return;
            }

            PushPostToWordPressJob::dispatch((int) $post->id)->afterCommit();
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'post_parent');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'post_parent');
    }

    public function meta(): HasMany
    {
        return $this->hasMany(Postmeta::class, 'post_id');
    }

    public function termTaxonomies(): BelongsToMany
    {
        return $this->belongsToMany(
            TermTaxonomy::class,
            'term_relationships',
            'object_id',
            'term_taxonomy_id',
        )->withPivot('term_order', 'team_id');
    }

    public function postType(): BelongsTo
    {
        return $this->belongsTo(PostType::class, 'post_type', 'name')
            ->where('post_types.team_id', $this->team_id);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('post_type', $type);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('post_status', self::STATUS_PUBLISH);
    }

    /**
     * Read a single meta value for the given key.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        $meta = $this->meta->firstWhere('meta_key', $key)
            ?? $this->meta()->where('meta_key', $key)->first();

        return $meta?->meta_value ?? $default;
    }

    /**
     * Create or update a meta value for the given key.
     */
    public function setMeta(string $key, mixed $value): Postmeta
    {
        return $this->meta()->updateOrCreate(
            ['meta_key' => $key],
            ['team_id' => $this->team_id, 'meta_value' => $value],
        );
    }

    /**
     * Meta values keyed by meta_key for API payloads.
     *
     * @return array<string, mixed>
     */
    public function metaAsArray(): array
    {
        return $this->meta->pluck('meta_value', 'meta_key')->toArray();
    }
}
