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
