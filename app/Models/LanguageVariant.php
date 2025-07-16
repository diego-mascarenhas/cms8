<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class LanguageVariant extends Model
{
    protected $fillable = [
        'code',
        'name',
        'base_language',
        'country_code',
    ];

    protected $appends = ['flag'];

    public $timestamps = false;

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            // Check if the user is authenticated before accessing currentTeam
            if (auth()->check() && auth()->user()->currentTeam) {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

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

    /**
     * Relación inversa con ContactLanguageVariant para source_language_code
     */
    public function sourceLanguageVariants()
    {
        return $this->hasMany(ContactLanguageVariant::class, 'source_language_code', 'code');
    }

    /**
     * Relación inversa con ContactLanguageVariant para target_language_code
     */
    public function targetLanguageVariants()
    {
        return $this->hasMany(ContactLanguageVariant::class, 'target_language_code', 'code');
    }
}
