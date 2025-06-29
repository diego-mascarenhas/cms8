<?php

namespace App\Models;

use App\Traits\HasSourceIcons;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory;
    use HasSourceIcons;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'user_id',
        'name',
        'surname',
        'email',
        'phone',
        'source_id',
        'birthday',
        'profile',
        'engagment',
        'country',
        'language',
        'creator_id',
        'responsible_id',
        'data',
        'status_id',
        'valoration_id',
    ];

    protected $casts = [
        'data' => 'object',
        'birthday' => 'date',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function enterprise()
    {
        return $this->hasOne(Enterprise::class, 'responsible_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country', 'id');
    }

    public function language()
    {
        return $this->belongsTo(Language::class, 'language', 'code');
    }

    /**
     * Get the language name accessor
     */
    public function getLanguageNameAttribute()
    {
        if ($this->relationLoaded('language')) {
            $languageRelation = $this->getRelation('language');
            if ($languageRelation) {
                return $languageRelation->name;
            }
        }
        
        if (isset($this->attributes['language'])) {
            $language = Language::where('code', $this->attributes['language'])->first();
            return $language ? $language->name : $this->attributes['language'];
        }
        
        return null;
    }

    /**
     * Get the language flag accessor
     */
    public function getLanguageFlagAttribute()
    {
        if ($this->relationLoaded('language')) {
            $languageRelation = $this->getRelation('language');
            if ($languageRelation) {
                return $languageRelation->flag;
            }
        }
        
        if (isset($this->attributes['language'])) {
            $language = Language::where('code', $this->attributes['language'])->first();
            return $language ? $language->flag : $this->attributes['language'];
        }
        
        return null;
    }

    public function languageVariants()
    {
        return $this->hasMany(ContactLanguageVariant::class);
    }

    /**
     * Get formatted language pairs for the view
     */
    public function getFormattedLanguagePairsAttribute()
    {
        \Log::info('Getting formatted language pairs for contact ID: ' . $this->id);
        \Log::info('Language variants count: ' . $this->languageVariants->count());

        $pairs = $this->languageVariants->map(function ($variant) {
            \Log::info('Processing variant: ' . $variant->id . ' - ' . $variant->source_language_code . ' -> ' . $variant->target_language_code);

            $sourceLanguage = $variant->sourceLanguage;
            $targetLanguage = $variant->targetLanguage;

            \Log::info('Source language: ' . ($sourceLanguage ? $sourceLanguage->name : 'null'));
            \Log::info('Target language: ' . ($targetLanguage ? $targetLanguage->name : 'null'));

            return [
                'source_language' => $variant->source_language_code,
                'target_language' => $variant->target_language_code,
                'source_language_text' => $sourceLanguage ? $sourceLanguage->name : $variant->source_language_code,
                'target_language_text' => $targetLanguage ? $targetLanguage->name : $variant->target_language_code,
                'is_native' => $variant->is_certified,
            ];
        });

        \Log::info('Formatted pairs: ' . json_encode($pairs));

        return $pairs;
    }

    public function sentimentHistories()
    {
        return $this->hasMany(ContactSentimentHistory::class);
    }

    public function currentSentiment()
    {
        return $this->hasOne(ContactSentimentHistory::class)->latest();
    }

    public function status()
    {
        return $this->belongsTo(ContactStatus::class);
    }

    public function valoration()
    {
        return $this->belongsTo(ContactValoration::class, 'valoration_id');
    }

    public function list60s()
    {
        return $this->hasMany(List60::class, 'contact_id');
    }

    public function getStatusLabelAttribute()
    {
        if ($this->status) {
            return '<span class="badge rounded-pill ' . $this->status->label_class . '">' . $this->status->name . '</span>';
        }

        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }

    /**
     * Get collaborators with incomplete data
     */
    public static function getIncompleteCollaborators($limit = 20, $teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);
        
        return static::where('team_id', $teamId)
            ->where(function ($query) {
                $query->whereNull('email')
                      ->orWhere('email', '')
                      ->orWhereNull('phone')
                      ->orWhere('phone', '');
            })
            ->with(['language', 'fares', 'softwares'])
            ->limit($limit)
            ->get()
            ->map(function ($contact) {
                $missingFields = [];
                $missingCount = 0;
                
                // Check required fields
                if (empty($contact->email)) {
                    $missingFields[] = 'email';
                    $missingCount++;
                }
                
                if (empty($contact->phone)) {
                    $missingFields[] = 'teléfono';
                    $missingCount++;
                }
                
                // Check optional but important fields
                if (empty($contact->language)) {
                    $missingFields[] = 'idioma';
                    $missingCount++;
                }
                
                if (empty($contact->profile)) {
                    $missingFields[] = 'perfil';
                    $missingCount++;
                }
                
                if (empty($contact->birthday)) {
                    $missingFields[] = 'cumpleaños';
                    $missingCount++;
                }
                
                // Check related data
                if ($contact->fares->count() === 0) {
                    $missingFields[] = 'servicios';
                    $missingCount++;
                }
                
                if ($contact->softwares->count() === 0) {
                    $missingFields[] = 'software';
                    $missingCount++;
                }
                
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'avatar' => "https://ui-avatars.com/api/?format=svg&name=" . urlencode($contact->name),
                    'missing_count' => $missingCount,
                    'missing_fields' => $missingFields,
                    'missing_text' => $missingCount > 0 ? 
                        ($missingCount === 1 ? 
                            "Falta: " . implode(', ', $missingFields) : 
                            "{$missingCount} campos por completar"
                        ) : 'Datos completos'
                ];
            })
            ->sortByDesc('missing_count')
            ->take($limit);
    }

    public static function getContactStats($teamId)
    {
        $statusLabels = [
            1 => 'Leads',
            2 => 'FollowUp',
            5 => 'Clients',
            6 => 'Finished',
        ];

        $contactStats = self::where('team_id', $teamId)
            ->whereIn('status_id', array_keys($statusLabels))
            ->get()
            ->groupBy('status_id')
            ->map(function ($group) {
                return $group->count();
            });

        $totalContacts = $contactStats->sum();

        $data = ['totalContacts' => $totalContacts];
        foreach ($statusLabels as $statusId => $label) {
            $count = $contactStats[$statusId] ?? 0;
            $percentage = $totalContacts > 0 ? round(($count / $totalContacts) * 100, 2) : 0;
            $data["total$label"] = $count;
            $data[lcfirst($label) . 'Percentage'] = $percentage;
        }

        $defaultData = [
            'totalContacts' => 0,
            'totalLeads' => 0,
            'leadsPercentage' => 0,
            'totalClients' => 0,
            'clientsPercentage' => 0,
            'totalFollowUp' => 0,
            'followUpPercentage' => 0,
            'totalFinished' => 0,
            'finishedPercentage' => 0,
        ];

        $finalData = array_merge($defaultData, $data);

        return $finalData;
    }

    public function actions()
    {
        return $this->hasMany(UserContactAction::class, 'contact_id');
    }

    public function calculateCurrentActionSeconds()
    {
        $latestAction = UserContactAction::where('contact_id', $this->id)
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();

        if (! $latestAction) {
            return 0;
        }

        $startTime = $latestAction->start_time;
        $endTime = Carbon::now();

        return $endTime->diffInSeconds($startTime);
    }

    public function calculateTotalAccumulatedSeconds()
    {
        $completedActions = UserContactAction::where('contact_id', $this->id)
            ->whereNotNull('end_time')
            ->get();

        $totalSeconds = 0;

        foreach ($completedActions as $action) {
            $totalSeconds += Carbon::parse($action->end_time)->diffInSeconds($action->start_time);
        }

        $currentActionSeconds = $this->calculateCurrentActionSeconds();
        $totalSeconds += $currentActionSeconds;

        return $totalSeconds;
    }

    public static function getTotalTeamMinutes()
    {
        $totalTeamSeconds = self::sum('duration_seconds');

        return round($totalTeamSeconds / 60);
    }

    public static function getTotalTeamTime()
    {
        $totalMinutes = self::getTotalTeamMinutes();
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return [
            'hours' => $hours,
            'minutes' => $minutes,
        ];
    }

    public function sources()
    {
        return $this->belongsToMany(Source::class, 'contact_sources')->withPivot('value');
    }

    public function primarySource()
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function enterprises(): BelongsToMany
    {
        return $this->belongsToMany(Enterprise::class, 'contact_enterprise')
            ->withPivot('position')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'contact_category');
    }

    public function softwares(): BelongsToMany
    {
        return $this->belongsToMany(Software::class, 'contact_softwares')
            ->withPivot('proficiency_level', 'notes')
            ->withTimestamps();
    }

    public function fares(): BelongsToMany
    {
        return $this->belongsToMany(Fare::class, 'contact_fare')
            ->withPivot('price', 'unit_id', 'currency_code', 'source_language_code', 'target_language_code')
            ->withTimestamps();
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'contact_topics')
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'contact_project')
            ->using(ContactProject::class)
            ->withPivot('message_sent', 'status', 'sent_at', 'viewed_at', 'responded_at', 'response_message', 'deleted_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at'); // Only get non-deleted relationships
    }

    public function list60(): HasOne
    {
        return $this->hasOne(List60::class);
    }

    public function isInList60(): bool
    {
        return $this->list60()->exists();
    }

    public function portfolios()
    {
        return $this->hasMany(ContactPortfolio::class);
    }

    /**
     * Get collaborator absences
     */
    public function absences()
    {
        return $this->hasMany(ContactAbsence::class);
    }

    /**
     * Get collaborator weekly availability
     */
    public function weeklyAvailability()
    {
        return $this->hasOne(ContactWeeklyAvailability::class);
    }

    /**
     * Get the WhatsApp formatted phone number from the contact
     *
     * @return string|null
     */
    public function getWhatsAppNumber()
    {
        // First try to get phone from direct field
        if ($this->phone) {
            $cleanNumber = preg_replace('/[^0-9]/', '', (string) $this->phone);

            return 'whatsapp:+' . $cleanNumber;
        }

        // If no direct phone, try to get from related user
        $relatedUser = $this->user()->first();

        if ($relatedUser && $relatedUser->phone) {
            $cleanNumber = preg_replace('/[^0-9]/', '', (string) $relatedUser->phone);

            return 'whatsapp:+' . $cleanNumber;
        }

        return null;
    }
}
