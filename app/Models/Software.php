<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Software extends Model
{
	use HasFactory, SoftDeletes;

	protected $table = 'software';

	protected $fillable = [
		'name',
		'team_id',
		'category_id',
	];

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
	 * Get the category that owns the software.
	 */
	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id');
	}

	/**
	 * Get the team that owns the software.
	 */
	public function team()
	{
		return $this->belongsTo(Team::class);
	}

	/**
	 * Get the contacts that use this software.
	 */
	public function contacts()
	{
		return $this->belongsToMany(Contact::class, 'contact_softwares')
			->withPivot('proficiency_level', 'notes')
			->withTimestamps();
	}

	/**
	 * Get software options for dropdowns and autocomplete.
	 */
	public static function getOptions()
	{
		return self::with('category')->get()->map(function ($data)
		{
			return [
				'id' => $data->id,
				'name' => $data->name,
				'category' => $data->category ? $data->category->name : '',
				'text' => $data->name.($data->category ? ' ('.$data->category->name.')' : ''),
			];
		});
	}
}
