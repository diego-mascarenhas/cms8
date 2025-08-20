<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TeamSetting extends Model
{
	protected $fillable = [
		'team_id',
		'key',
		'value',
		'type',
		'group',
		'is_encrypted',
		'description',
	];

	protected $casts = [
		'is_encrypted' => 'boolean',
	];

	public function team()
	{
		return $this->belongsTo(Team::class);
	}

	public function getValueAttribute($value)
	{
		if ($this->is_encrypted && $value)
		{
			$value = Crypt::decryptString($value);
		}

		switch ($this->type)
		{
			case 'boolean':
				return filter_var($value, FILTER_VALIDATE_BOOLEAN);
			case 'integer':
				return (int) $value;
			case 'json':
				return json_decode($value, true);
			default:
				return $value;
		}
	}

	public function setValueAttribute($value)
	{
		if ($this->type === 'json' && is_array($value))
		{
			$value = json_encode($value);
		}

		if ($this->is_encrypted && $value)
		{
			$value = Crypt::encryptString($value);
		}

		$this->attributes['value'] = $value;
	}
}
