<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
	protected $primaryKey = 'code';

	public $timestamps = false;

	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = ['code', 'name'];

	/**
	 * Get the flag code for this language
	 * Maps language codes to country codes for flag display
	 */
	public function getFlagAttribute()
	{
		$flagMapping = [
			'es' => 'es',  // Spanish -> Spain
			'en' => 'us',  // English -> United States (could be 'gb' for UK)
			'fr' => 'fr',  // French -> France
			'de' => 'de',  // German -> Germany
			'it' => 'it',  // Italian -> Italy
			'pt' => 'pt',  // Portuguese -> Portugal (could be 'br' for Brazil)
			'ca' => 'es',  // Catalan -> Spain (uses Spanish flag)
		];

		return $flagMapping[$this->code] ?? $this->code;
	}

	/**
	 * Get contacts that use this language
	 */
	public function contacts()
	{
		return $this->hasMany(Contact::class, 'language', 'code');
	}
}
