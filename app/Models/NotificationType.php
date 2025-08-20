<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationType extends Model
{
	use HasFactory;

	protected $fillable = [
		'name',
		'template_subject',
		'template_body',
		'is_customizable',
		'is_active',
	];

	protected $casts = [
		'is_customizable' => 'boolean',
		'is_active' => 'boolean',
	];

	/**
	 * Get all notifications of this type
	 */
	public function notifications()
	{
		return $this->hasMany(Notification::class, 'type_id');
	}

	/**
	 * Get active notification types for options
	 */
	public static function getActiveOptions()
	{
		return self::where('is_active', true)
			->orderBy('name')
			->get()
			->map(function ($type)
			{
				return [
					'id' => $type->id,
					'name' => $type->name,
				];
			});
	}

	/**
	 * Replace placeholders in template
	 */
	public function replacePlaceholders(array $data, string $field = 'template_body')
	{
		$template = $this->$field;

		if (! $template)
		{
			return '';
		}

		foreach ($data as $key => $value)
		{
			$template = str_replace("{{$key}}", $value, $template);
		}

		return $template;
	}
}
