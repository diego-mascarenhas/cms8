<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
	use HasFactory;

	protected $fillable = [
		'name',
		'description',
		'price',
		'currency_id',
		'category_id',
		'status',
		'whatsapp_enabled',
		'team_id',
	];

	protected $casts = [
		'price' => 'decimal:2',
		'status' => 'boolean',
		'whatsapp_enabled' => 'boolean',
	];

	/**
	 * The "booted" method of the model.
	 */
	protected static function booted()
	{
		static::addGlobalScope('team', function (Builder $builder)
		{
			if (auth()->check())
			{
				$builder->where('team_id', auth()->user()->currentTeam->id);
			}
		});
	}

	/**
	 * Get the category that owns the product.
	 */
	public function category()
	{
		return $this->belongsTo(Category::class);
	}

	/**
	 * Get the currency that owns the product.
	 */
	public function currency()
	{
		return $this->belongsTo(Currency::class);
	}

	/**
	 * Get the team that owns the product.
	 */
	public function team()
	{
		return $this->belongsTo(Team::class);
	}

	/**
	 * Scope a query to only include active products.
	 */
	public function scopeActive($query)
	{
		return $query->where('status', true);
	}

	/**
	 * Scope a query to only include WhatsApp enabled products.
	 */
	public function scopeWhatsAppEnabled($query)
	{
		return $query->where('whatsapp_enabled', true);
	}
}
