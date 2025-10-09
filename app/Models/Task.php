<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Task extends Model implements HasMedia
{
	use HasFactory, SoftDeletes, InteractsWithMedia;

	protected $fillable = [
		'team_id',
		'board_id',
		'category_id',
		'responsible_id',
		'title',
		'description',
		'estimated_hours',
		'start_date',
		'due_date',
		'status_id',
		'order',
	];

	protected $casts = [
		'start_date' => 'date',
		'due_date' => 'date',
		'estimated_hours' => 'decimal:2',
	];

	protected static function booted()
	{
		static::addGlobalScope('team', function (Builder $builder)
		{
			if (auth()->check() && auth()->user()->currentTeam)
			{
				$builder->where('team_id', auth()->user()->currentTeam->id);
			}
		});
	}

	public function responsible()
	{
		return $this->belongsTo(User::class, 'responsible_id');
	}

	public function status()
	{
		return $this->belongsTo(TaskStatus::class);
	}

	public function board()
	{
		return $this->belongsTo(TaskBoard::class);
	}

	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id');
	}

	public function getStatusLabelAttribute()
	{
		if ($this->status)
		{
			return '<span class="badge rounded-pill '.$this->status->label_class.'">'.$this->status->translated_name.'</span>';
		}

		return '<span class="badge rounded-pill bg-label-secondary">'.__('task_status.UNKNOWN').'</span>';
	}

	public function getTranslatedStatusAttribute()
	{
		return $this->status ? $this->status->translated_name : __('task_status.UNKNOWN');
	}

	public function scopePendingForUser($query, $userId)
	{
		return $query->where('responsible_id', $userId)
			->whereHas('status', function ($q)
			{
				$q->whereNotIn('name', ['DONE']);
			})
			->orderBy('due_date', 'asc');
	}

	/**
	 * Default ordering for tasks
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function scopeDefaultOrder($query)
	{
		return $query->orderBy('status_id', 'asc')->orderBy('due_date', 'asc');
	}

	/**
	 * Register media collections for this model
	 */
	public function registerMediaCollections(): void
	{
		$this->addMediaCollection('attachments')
			->singleFile(); // Only one attachment per task
	}
}
