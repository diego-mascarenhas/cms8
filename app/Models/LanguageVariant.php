<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguageVariant extends Model
{
    protected $fillable = [
        'code',
        'name',
        'base_language',
        'country_code'
    ];

    protected $appends = ['flag'];

    public $timestamps = false;

    /**
     * Obtiene todas las variantes para un idioma base
     */
    public static function getVariantsFor($baseLanguage)
    {
        return self::where('base_language', strtolower($baseLanguage))
            ->orderBy('name')
            ->get();
    }

    /**
     * Relación con el idioma base
     */
    public function baseLanguage()
    {
        return $this->belongsTo(Language::class, 'base_language', 'code');
    }
    
    /**
     * Set the base language to lowercase
     */
    public function setBaseLanguageAttribute($value)
    {
        $this->attributes['base_language'] = strtolower($value);
    }
    
    /**
     * Set the country code to uppercase
     */
    public function setCountryCodeAttribute($value)
    {
        $this->attributes['country_code'] = $value ? strtoupper($value) : null;
    }
    
    /**
     * Get flag code from country code
     */
    public function getFlagAttribute()
    {
        return $this->country_code;
    }
} 