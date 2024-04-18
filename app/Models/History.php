<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class History extends Model
{
	use HasFactory;

	protected $table = 'history';

	protected static function boot()
	{
		parent::boot();

		static::addGlobalScope('UsersMessages', function (Builder $builder)
		{
			$builder->where('answer', '!=', '__call_action__')
				->where('answer', 'not like', '_event_voice_note__%');
		});
	}
}