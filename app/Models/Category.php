<?php

namespace App\Models;

use App\Support\ContentsSectionCategoryData;
use App\Support\TeamContentsApiCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Tags\HasTags;

class Category extends Model
{
    use HasFactory;
    use HasTags;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'module_id',
        'team_id',
        'description',
        'data',
        'parent_id',
        'order',
        'status',
    ];

    protected $casts = [
        'data' => 'array',
        'status' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (Category $category)
        {
            self::maybeBumpTeamContentsApiCache($category);
        });

        static::deleted(function (Category $category)
        {
            self::maybeBumpTeamContentsApiCache($category);
        });
    }

    private static function maybeBumpTeamContentsApiCache(Category $category): void
    {
        if (! $category->team_id)
        {
            return;
        }

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        if (! $contentsModuleId || (int) $category->module_id !== (int) $contentsModuleId)
        {
            return;
        }

        TeamContentsApiCache::bumpTeam((int) $category->team_id);
    }

    /**
     * Visibility flags for standard fields on the contents form (from {@see Category::$data} `content_form`).
     *
     * @return array{
     *     show_title: bool,
     *     show_subtitle: bool,
     *     show_url: bool,
     *     show_main_content: bool,
     *     show_featured: bool,
     *     show_seo: bool,
     *     show_multimedia: bool
     * }
     */
    public function contentFormVisibility(): array
    {
        return ContentsSectionCategoryData::mergeContentFormVisibility($this->data['content_form'] ?? null);
    }

    /**
     * Locale codes enabled for the contents form for this section (from {@see Category::$data} `content_locales`).
     *
     * @return list<string>
     */
    public function contentFormLocales(): array
    {
        return ContentsSectionCategoryData::mergeContentLocalesFromStorage($this->data['content_locales'] ?? null);
    }

    /**
     * Whether this contents section category exposes the history timeline for external consumers (see {@see Category::$data} `page_sections`).
     */
    public function contentsPageSectionHistoryTimeline(): bool
    {
        $pageSections = $this->data['page_sections'] ?? null;
        if (! is_array($pageSections))
        {
            return false;
        }

        return ! empty($pageSections['history_timeline']);
    }

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get direct child categories.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('order')
            ->orderBy('name');
    }

    /**
     * Get all descendants (recursive).
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get team that owns this category.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get users associated with this category.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'category_user', 'category_id', 'user_id');
    }

    /**
     * Get messages in this category.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get invoice items in this category.
     */
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'category_id');
    }

    /**
     * Get services in this category.
     */
    public function services()
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Products using this category (team-scoped products table).
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Tasks using this category.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'category_id');
    }

    /**
     * Projects using this category.
     */
    public function projects()
    {
        return $this->hasMany(Project::class, 'category_id');
    }

    /**
     * Team file records using this category.
     */
    public function teamFiles()
    {
        return $this->hasMany(TeamFile::class, 'category_id');
    }

    /**
     * Get the module this category belongs to.
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get contacts associated with this category.
     */
    public function contacts()
    {
        return $this->belongsToMany(\App\Models\Contact::class, 'contact_category', 'category_id', 'contact_id');
    }

    /**
     * Contents using this category as primary category_id (not section).
     */
    public function contentByPrimaryCategory()
    {
        return $this->hasMany(Content::class, 'category_id');
    }

    /**
     * Get contents associated with this category as section.
     */
    public function contents()
    {
        $query = $this->hasMany(\App\Models\Content::class, 'section_category_id');

        // Get ordering configuration from category data
        $ordering = $this->getContentOrdering();

        // Apply ordering based on configuration
        foreach ($ordering as $orderBy)
        {
            if (isset($orderBy['column']) && isset($orderBy['direction']))
            {
                $query->orderBy($orderBy['column'], $orderBy['direction']);
            }
        }

        return $query;
    }

    /**
     * Get content ordering configuration for this category.
     * Returns array of ordering rules from category data or default.
     */
    public function getContentOrdering(): array
    {
        // Check if custom ordering is configured in category data
        if (isset($this->data['content_ordering']) && is_array($this->data['content_ordering']))
        {
            return $this->data['content_ordering'];
        }

        // Default ordering: order field first, then created_at desc
        return [
            ['column' => 'order', 'direction' => 'asc'],
            ['column' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * Set content ordering configuration for this category.
     */
    public function setContentOrdering(array $ordering): void
    {
        $data = $this->data ?? [];
        $data['content_ordering'] = $ordering;
        $this->data = $data;
    }

    /**
     * Get active contents associated with this category as section.
     */
    public function activeContents()
    {
        return $this->contents()->where('status', 3);
    }

    /**
     * Get content field configs associated with this category as section.
     */
    public function contentFieldConfigs()
    {
        return $this->hasMany(\App\Models\ContentFieldConfig::class, 'section_category_id')
            ->where('is_active', true)
            ->orderBy('order');
    }

    /**
     * Get slug from data JSON.
     */
    public function getSlugAttribute(): ?string
    {
        return $this->data['slug'] ?? null;
    }

    /**
     * Set slug in data JSON.
     */
    public function setSlugAttribute(?string $value): void
    {
        $data = $this->data ?? [];
        if ($value === null)
        {
            unset($data['slug']);
        } else
        {
            $data['slug'] = $value;
        }
        $this->attributes['data'] = json_encode($data);
    }

    /**
     * Get template from data JSON.
     */
    public function getTemplateAttribute(): ?string
    {
        return $this->data['template'] ?? null;
    }

    /**
     * Set template in data JSON.
     */
    public function setTemplateAttribute(?string $value): void
    {
        $data = $this->data ?? [];
        if ($value === null)
        {
            unset($data['template']);
        } else
        {
            $data['template'] = $value;
        }
        $this->attributes['data'] = json_encode($data);
    }

    /**
     * Get formatted status.
     */
    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    /**
     * Get full path name (including parent names).
     */
    public function getFullPathAttribute()
    {
        $path = $this->name;
        $category = $this;

        while ($category->parent)
        {
            $category = $category->parent;
            $path = $category->name.' > '.$path;
        }

        return $path;
    }

    /**
     * Get options for dropdowns.
     */
    public static function getOptions($teamId, $parentId = null, $moduleId = null)
    {
        $query = self::query()->where('team_id', $teamId);

        if (! is_null($parentId))
        {
            $query->where('parent_id', $parentId);
        }

        if (! is_null($moduleId))
        {
            $query->where('module_id', $moduleId);
        }

        return $query->orderBy('name')->get()->map(function ($data)
        {
            return [
                'id' => $data->id,
                'name' => $data->name,
                'full_path' => $data->full_path,
            ];
        });
    }

    /**
     * Scope a query to only include categories of a specific module.
     */
    public function scopeModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope a query to only include categories for a specific team.
     */
    public function scopeTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Count of records that block deleting this category (same scope as destroy checks).
     */
    public function blockingDeleteUsageCount(): int
    {
        return $this->invoiceItems()->count()
            + $this->teamFiles()->count()
            + $this->messages()->count()
            + $this->products()->count()
            + $this->tasks()->count()
            + $this->projects()->count()
            + $this->contents()->count()
            + $this->contentByPrimaryCategory()->count()
            + Multimedia::where('category_id', $this->id)->count()
            + Software::where('category_id', $this->id)->count()
            + ServiceType::where('category_id', $this->id)->count();
    }

    /**
     * Get all categories for a specific team and module, organized in a hierarchical structure.
     */
    public static function getHierarchy($teamId, $moduleId = null)
    {
        $query = self::query()
            ->where('team_id', $teamId)
            ->whereNull('parent_id')
            ->with(['children.children']) // Load up to 3 levels deep
            ->orderBy('order')
            ->orderBy('name');

        if (! is_null($moduleId))
        {
            $query->where('module_id', $moduleId);
        }

        return $query->get();
    }
}
