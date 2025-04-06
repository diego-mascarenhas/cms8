<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'categories';

    protected $fillable = ['name', 'module_id', 'description', 'data', 'parent_id', 'order', 'status'];

    protected $casts = [
        'data' => 'object',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'category_user', 'category_id', 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'category_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get the module this category belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get options for dropdowns
     */
    public static function getOptions($parentId = null, $moduleId = null)
    {
        $query = self::query();

        if (!is_null($parentId)) {
            $query->where('parent_id', $parentId);
        }

        if (!is_null($moduleId)) {
            $query->where('module_id', $moduleId);
        }

        return $query->get()->map(function ($data) {
            return [
                'id' => $data->id,
                'name' => $data->name,
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
     * Get all categories for a specific module, organized in a hierarchical structure.
     */
    public static function getHierarchy($moduleId = null)
    {
        $query = self::query()->whereNull('parent_id')->with('children');
        
        if (!is_null($moduleId)) {
            $query->where('module_id', $moduleId);
        }
        
        return $query->get();
    }
}
