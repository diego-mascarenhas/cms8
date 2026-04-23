<?php

namespace App\Models;

use App\Support\TeamContentsApiCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Website content row. Translatable copy lives in JSON columns ({@see $casts}) per locale key (e.g. es).
 * {@see $title} is not the main body: use {@see $content} for the primary HTML/text body. {@see $data}
 * holds ContentFieldConfig-driven fields (e.g. event_year, image_url for template timeline_item).
 */
class Content extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'contents';

    protected $fillable = [
        'team_id',
        'section_category_id',
        'category_id',
        'template',
        'order',
        'status',
        'featured',
        'featured_slide',
        'featured_modal',
        'data',
        'title',
        'subtitle',
        'url',
        'content',
        'seo_title',
        'seo_keywords',
        'seo_description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data' => 'array',
        'title' => 'array',
        'subtitle' => 'array',
        'url' => 'array',
        'content' => 'array',
        'seo_title' => 'array',
        'seo_keywords' => 'array',
        'seo_description' => 'array',
        'featured' => 'boolean',
        'featured_slide' => 'boolean',
        'featured_modal' => 'boolean',
        'status' => 'integer',
        'order' => 'integer',
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

        static::creating(function (self $content)
        {
            if (auth()->check())
            {
                $content->team_id = $content->team_id ?? auth()->user()->currentTeam->id;
                $content->created_by = $content->created_by ?? auth()->id();
                $content->updated_by = $content->updated_by ?? auth()->id();
            }
        });

        static::updating(function (self $content)
        {
            if (auth()->check())
            {
                $content->updated_by = auth()->id();
            }
        });

        static::saved(function (self $content)
        {
            if ($content->team_id)
            {
                TeamContentsApiCache::bumpTeam((int) $content->team_id);
            }
        });

        static::deleted(function (self $content)
        {
            if ($content->team_id)
            {
                TeamContentsApiCache::bumpTeam((int) $content->team_id);
            }
        });

        static::restored(function (self $content)
        {
            if ($content->team_id)
            {
                TeamContentsApiCache::bumpTeam((int) $content->team_id);
            }
        });

        static::forceDeleted(function (self $content)
        {
            if ($content->team_id)
            {
                TeamContentsApiCache::bumpTeam((int) $content->team_id);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sectionCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'section_category_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function multimedia(): BelongsToMany
    {
        return $this->belongsToMany(Multimedia::class, 'content_multimedia', 'content_id', 'multimedia_id')
            ->withPivot('language', 'type', 'order')
            ->withTimestamps()
            ->orderByPivot('order');
    }

    /**
     * Get the section items for this content.
     */
    public function sectionItems(): HasMany
    {
        return $this->hasMany(ContentSectionItem::class)
            ->where('is_active', true)
            ->orderBy('order');
    }

    /**
     * Get all section items (including inactive) for this content.
     */
    public function allSectionItems(): HasMany
    {
        return $this->hasMany(ContentSectionItem::class)
            ->orderBy('order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Title for admin lists: prefers app locale, then Spanish, then the section’s configured locales, then any non-empty translation.
     */
    public function resolveAdministrativeTitle(): ?string
    {
        $raw = $this->title;

        if (is_string($raw))
        {
            $trimmed = trim($raw);

            return $trimmed === '' ? null : $trimmed;
        }

        if (! is_array($raw) || $raw === [])
        {
            return null;
        }

        $locale = app()->getLocale();
        $fromSection = [];
        if ($this->relationLoaded('sectionCategory') && $this->sectionCategory)
        {
            $fromSection = $this->sectionCategory->contentFormLocales();
        }

        $candidates = array_values(array_unique(array_merge(
            [$locale, 'es'],
            $fromSection,
            array_keys($raw),
        )));

        foreach ($candidates as $code)
        {
            if (! is_string($code) || $code === '')
            {
                continue;
            }

            $v = $raw[$code] ?? null;
            if (is_string($v))
            {
                $trimmed = trim($v);
                if ($trimmed !== '')
                {
                    return $trimmed;
                }
            }
        }

        return null;
    }

    /**
     * Get translatable field value for current locale
     */
    public function getTranslatable(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $value = $this->$field;

        if (is_array($value))
        {
            return $value[$locale] ?? $value['es'] ?? $value[array_key_first($value)] ?? null;
        }

        return $value;
    }

    /**
     * Set translatable field value for current locale
     */
    public function setTranslatable(string $field, string $value, ?string $locale = null): void
    {
        $locale = $locale ?? app()->getLocale();
        $current = $this->$field ?? [];

        if (! is_array($current))
        {
            $current = [];
        }

        $current[$locale] = $value;
        $this->$field = $current;
    }

    /**
     * Get data field value
     */
    public function getDataField(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set data field value
     */
    public function setDataField(string $key, $value): void
    {
        $data = $this->data ?? [];
        $data[$key] = $value;
        $this->data = $data;
    }

    /**
     * Retrieve the model for route model binding.
     * This ensures the global scope is applied correctly.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        return $this->where($field, $value)->first();
    }
}
