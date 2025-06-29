<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactLanguageVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'source_language_code',
        'target_language_code',
        'proficiency_level',
        'is_certified',
        'notes',
    ];

    protected $casts = [
        'is_certified' => 'boolean',
        'proficiency_level' => 'integer',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check()) {
                $builder->whereHas('contact', function ($query) {
                    $query->where('team_id', auth()->user()->currentTeam->id);
                });
            }
        });
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function sourceLanguage()
    {
        return $this->belongsTo(LanguageVariant::class, 'source_language_code', 'code');
    }

    public function targetLanguage()
    {
        return $this->belongsTo(LanguageVariant::class, 'target_language_code', 'code');
    }

    /**
     * Get language combinations with less than the specified number of collaborators
     */
    public static function getCombinationsWithFewCollaborators($maxCount = 10, $limit = 6, $teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);
        
        return static::select([
                'source_language_code', 
                'target_language_code'
            ])
            ->selectRaw('COUNT(DISTINCT contact_id) as collaborator_count')
            ->whereHas('contact', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->groupBy('source_language_code', 'target_language_code')
            ->havingRaw('COUNT(DISTINCT contact_id) < ?', [$maxCount])
            ->orderBy('collaborator_count', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($combination) {
                // Extract base language codes (e.g., 'es' from 'es-MX')
                $sourceBaseCode = explode('-', $combination->source_language_code)[0];
                $targetBaseCode = explode('-', $combination->target_language_code)[0];
                
                // Get language names using base codes
                $sourceLanguage = Language::where('code', $sourceBaseCode)->first();
                $targetLanguage = Language::where('code', $targetBaseCode)->first();
                
                return [
                    'source_code' => $combination->source_language_code,
                    'target_code' => $combination->target_language_code,
                    'source_name' => $sourceLanguage ? $sourceLanguage->name : ucfirst($sourceBaseCode),
                    'target_name' => $targetLanguage ? $targetLanguage->name : ucfirst($targetBaseCode),
                    'source_flag' => static::getLanguageFlag($combination->source_language_code),
                    'target_flag' => static::getLanguageFlag($combination->target_language_code),
                    'count' => $combination->collaborator_count
                ];
            });
    }

    /**
     * Helper method to get flag code from language code
     */
    public static function getLanguageFlag($languageCode)
    {
        $flagMap = [
            'es-ES' => 'es',
            'es-MX' => 'mx',
            'es-AR' => 'ar',
            'en-US' => 'us',
            'en-GB' => 'gb',
            'fr-FR' => 'fr',
            'de-DE' => 'de',
            'it-IT' => 'it',
            'pt-BR' => 'br',
            'pt-PT' => 'pt',
            'zh-CN' => 'cn',
            'ja-JP' => 'jp',
            'ko-KR' => 'kr'
        ];
        
        return $flagMap[$languageCode] ?? strtolower(explode('-', $languageCode)[1] ?? explode('-', $languageCode)[0]);
    }
}
