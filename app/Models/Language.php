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
            'ar' => 'sa',  // Arabic -> Saudi Arabia
            'bg' => 'bg',  // Bulgarian -> Bulgaria
            'ca' => 'es',  // Catalan -> Spain
            'cs' => 'cz',  // Czech -> Czech Republic
            'da' => 'dk',  // Danish -> Denmark
            'de' => 'de',  // German -> Germany
            'el' => 'gr',  // Greek -> Greece
            'en' => 'us',  // English -> United States
            'es' => 'es',  // Spanish -> Spain
            'et' => 'ee',  // Estonian -> Estonia
            'eu' => 'es',  // Basque -> Spain
            'fi' => 'fi',  // Finnish -> Finland
            'fr' => 'fr',  // French -> France
            'gl' => 'es',  // Galician -> Spain
            'he' => 'il',  // Hebrew -> Israel
            'hi' => 'in',  // Hindi -> India
            'hr' => 'hr',  // Croatian -> Croatia
            'hu' => 'hu',  // Hungarian -> Hungary
            'it' => 'it',  // Italian -> Italy
            'ja' => 'jp',  // Japanese -> Japan
            'ko' => 'kr',  // Korean -> South Korea
            'lt' => 'lt',  // Lithuanian -> Lithuania
            'lv' => 'lv',  // Latvian -> Latvia
            'mt' => 'mt',  // Maltese -> Malta
            'nb' => 'no',  // Norwegian -> Norway
            'nl' => 'nl',  // Dutch -> Netherlands
            'pl' => 'pl',  // Polish -> Poland
            'pt' => 'pt',  // Portuguese -> Portugal
            'ro' => 'ro',  // Romanian -> Romania
            'ru' => 'ru',  // Russian -> Russia
            'sk' => 'sk',  // Slovak -> Slovakia
            'sl' => 'si',  // Slovenian -> Slovenia
            'sv' => 'se',  // Swedish -> Sweden
            'th' => 'th',  // Thai -> Thailand
            'tr' => 'tr',  // Turkish -> Turkey
            'uk' => 'ua',  // Ukrainian -> Ukraine
            'vi' => 'vn',  // Vietnamese -> Vietnam
            'zh' => 'cn',  // Chinese -> China
        ];

        return $flagMapping[$this->code] ?? strtolower($this->code);
    }

    /**
     * Get contacts that use this language
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class, 'language', 'code');
    }

    /**
     * Get certifications that belong to this language
     */
    public function certifications()
    {
        return $this->hasMany(Certification::class, 'language', 'code');
    }

    /**
     * Get top languages by collaborator count
     */
    public static function getTopLanguages($limit = 5, $teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        return static::select(['languages.code', 'languages.name'])
            ->selectRaw('COUNT(contacts.id) as collaborator_count')
            ->join('contacts', 'languages.code', '=', 'contacts.language')
            ->where('contacts.team_id', $teamId)
            ->groupBy('languages.code', 'languages.name')
            ->orderBy('collaborator_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($language)
            {
                return [
                    'code' => $language->code,
                    'name' => $language->name,
                    'count' => $language->collaborator_count,
                    'flag' => $language->flag,
                ];
            });
    }

    /**
     * Get count of active languages (languages with at least 1 collaborator)
     */
    public static function getActiveLanguagesCount($teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        return static::select('languages.code')
            ->join('contacts', 'languages.code', '=', 'contacts.language')
            ->where('contacts.team_id', $teamId)
            ->groupBy('languages.code')
            ->get()
            ->count();
    }
}
