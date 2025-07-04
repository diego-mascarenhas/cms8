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
            ->map(function ($language) {
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
