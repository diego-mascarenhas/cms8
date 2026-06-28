<?php

namespace App\Models;

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

    /**
     * Primary keys that still exist and are not soft-deleted. Use before writing the contact_category pivot.
     *
     * @param  iterable<int|string|null>  $ids
     * @return list<int>
     */
    public static function onlyExistingIds(iterable $ids): array
    {
        $normalized = [];
        foreach ($ids as $id)
        {
            $int = (int) $id;
            if ($int > 0)
            {
                $normalized[$int] = $int;
            }
        }
        if ($normalized === [])
        {
            return [];
        }

        return static::query()->whereIn('id', array_values($normalized))->pluck('id')->all();
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
     * Messages classified by this category (future news type via messages.category_id).
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Campaign messages that send to contacts in this contact category.
     */
    public function messagesByContactCategory()
    {
        return $this->belongsToMany(Message::class, 'message_categories');
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
            + $this->services()->count()
            + $this->teamFiles()->count()
            + $this->messages()->count()
            + $this->messagesByContactCategory()->count()
            + $this->products()->count()
            + $this->tasks()->count()
            + $this->projects()->count()
            + Multimedia::where('category_id', $this->id)->count()
            + Software::where('category_id', $this->id)->count();
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
