<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
	use HasFactory;

	protected $table = 'service_types';

	protected $fillable = [
		'name',
		'category_id',
		'description',
		'data',
		'currency_id',
		'convert_to',
		'price',
		'discount',
		'frequency',
		'order',
		'status',
	];

	protected $casts = [
		'data' => 'array',
		'price' => 'decimal:2',
		'discount' => 'decimal:2',
		'status' => 'boolean',
	];

	/**
	 * Get the category this service type belongs to.
	 */
	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id');
	}

	/**
	 * Get services of this service type.
	 */
	public function services()
	{
		return $this->hasMany(Service::class, 'service_type_id');
	}

	/**
	 * Get categories of this service type.
	 */
	public function categories()
	{
		return $this->hasMany(Category::class, 'service_type_id');
	}

	/**
	 * Get the currency.
	 */
	public function currency()
	{
		return $this->belongsTo(Currency::class, 'currency_id');
	}
}
