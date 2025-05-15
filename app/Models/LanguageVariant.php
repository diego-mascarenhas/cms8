<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguageVariant extends Model
{
    protected $fillable = [
        'code',
        'name',
        'base_language',
        'country_code',
        'native_name',
        'flag'
    ];

    public $timestamps = false;

    /**
     * Obtiene todas las variantes para un idioma base
     */
    public static function getVariantsFor($baseLanguage)
    {
        return self::where('base_language', $baseLanguage)
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
} 