<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
	use HasFactory;
	use SoftDeletes;

	public $timestamps = true;

	protected $table = 'messages';

	protected $fillable = ['name', 'type_id', 'category_id', 'template_id', 'text', 'status_id', 'team_id'];

	protected $casts = [
		'status_id' => 'boolean',
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

		static::creating(function ($model)
		{
			if (! $model->team_id && auth()->check())
			{
				$model->team_id = auth()->user()->currentTeam->id;
			}
		});
	}

	public function team()
	{
		return $this->belongsTo(Team::class);
	}

	public function type()
	{
		return $this->belongsTo(MessageType::class);
	}

	public function category()
	{
		return $this->belongsTo(Category::class);
	}

	public function template()
	{
		return $this->belongsTo(Template::class);
	}

	public function deliveries()
	{
		return $this->hasMany(MessageDelivery::class);
	}
}
