<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
	protected $table = 'countries';

	public $timestamps = false;

	protected $fillable = ['id', 'name', 'code'];

	protected $primaryKey = 'id';

	public $incrementing = false;

	protected $keyType = 'integer';

	public function setCodeAttribute($value)
	{
		$this->attributes['code'] = strtolower($value);
	}

	public function contacts()
	{
		return $this->hasMany(Contact::class, 'country', 'code');
	}

	public static function getOptions()
	{
		return self::orderBy('name')->pluck('name', 'code');
	}
}
