<?php

namespace App\Models;

use App\Helpers\AvatarHelper;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppProfilePhotoStore;
use App\Traits\HasSourceIcons;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contact extends Model implements HasMedia
{
    use HasFactory;
    use HasSourceIcons;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'user_id',
        'current_enterprise_id',
        'name',
        'surname',
        'email',
        'phone',
        'source_id',
        'birthday',
        'profile',
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
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        // Visibility: only narrow list for roles that are not team-wide CRM viewers
        static::addGlobalScope('ownership', function (Builder $builder)
        {
            if (auth()->check())
            {
                $user = auth()->user();
                if ($user->hasRole(['admin', 'root', 'collaborator', 'marketing']))
                {
                    return;
                }

                $builder->where(function ($query) use ($user)
                {
                    $query->where('responsible_id', $user->id)
                        ->orWhere('user_id', $user->id);
                });
            }
        });

        static::updating(function (Contact $contact): void
        {
            if ($contact->isDirty('email'))
            {
                $contact->forgetChannelCheck('email');
            }
            if ($contact->isDirty('phone'))
            {
                $contact->forgetChannelCheck('whatsapp');
            }
        });
    }

    /**
     * Per-contact preference for the chat header assistant toggle / contact form.
     * When false, inbound WhatsApp auto-replies are blocked while team auto-respond is on
     * ({@see \App\Services\TeamInboundAssistantPolicy}). Does not override a disabled team global.
     */
    public function allowsInboundChatAssistant(): bool
    {
        $data = $this->chatAssistantData();
        if (! array_key_exists('chat_assistant_ai_enabled', $data))
        {
            return true;
        }

        return filter_var($data['chat_assistant_ai_enabled'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Forced team prompt for inbound WhatsApp replies. Empty means team default / router.
     */
    public function inboundChatAssistantPromptKey(): ?string
    {
        $data = $this->chatAssistantData();
        if (! array_key_exists('chat_assistant_prompt_key', $data))
        {
            return null;
        }

        $key = trim((string) $data['chat_assistant_prompt_key']);

        return $key !== '' ? $key : null;
    }

    /**
     * Pin (or clear) the inbound WhatsApp prompt after the assistant commits a flow.
     */
    public function pinInboundChatAssistantPrompt(?string $routingKey): void
    {
        $data = $this->chatAssistantData();
        $key = $routingKey !== null ? trim($routingKey) : '';
        if ($key !== '')
        {
            $data['chat_assistant_prompt_key'] = $key;
            $data['chat_assistant_ai_enabled'] = true;
        } else
        {
            unset($data['chat_assistant_prompt_key']);
        }

        $this->data = (object) $data;
        if ($this->exists)
        {
            $this->save();
        }
    }

    /**
     * WhatsApp profile photo when we have one for this phone; otherwise generated initials.
     */
    public function avatarUrl(int $size = 100): string
    {
        $teamId = (int) ($this->team_id ?? 0);
        if ($teamId > 0)
        {
            $store = app(WhatsAppProfilePhotoStore::class);
            foreach ($this->whatsAppDigitCandidates() as $phone)
            {
                $url = $store->publicUrl($teamId, $phone);
                if ($url !== null)
                {
                    return $url;
                }
            }
        }

        $name = trim((string) $this->name.' '.(string) ($this->surname ?? ''));

        return AvatarHelper::generate($name !== '' ? $name : 'Contacto', $size);
    }

    /**
     * Stored profile photo: the linked user, or a WhatsApp photo for this phone.
     */
    public function storedPhotoUrl(): ?string
    {
        $userPhoto = $this->user?->profile_photo_url;
        if (is_string($userPhoto) && $userPhoto !== '')
        {
            return $userPhoto;
        }

        $teamId = (int) ($this->team_id ?? 0);
        if ($teamId < 1)
        {
            return null;
        }

        $store = app(WhatsAppProfilePhotoStore::class);
        foreach ($this->whatsAppDigitCandidates() as $phone)
        {
            $url = $store->publicUrl($teamId, $phone);
            if (is_string($url) && $url !== '')
            {
                return $url;
            }
        }

        return null;
    }

    /**
     * Digits used to match a stored WhatsApp profile photo.
     */
    public function whatsAppDigits(): string
    {
        $phone = preg_replace('/[^0-9]/', '', (string) ($this->phone ?? '')) ?? '';
        if ($phone !== '')
        {
            return $phone;
        }

        if (! $this->relationLoaded('user'))
        {
            if (! $this->exists)
            {
                return '';
            }

            $this->loadMissing('user');
        }

        return preg_replace('/[^0-9]/', '', (string) ($this->user?->phone ?? '')) ?? '';
    }

    /**
     * Phone variants that may already have a stored WhatsApp photo.
     * Argentina mobiles are often saved as 54… in the CRM and 549… on WhatsApp.
     *
     * @return array<int, string>
     */
    public function whatsAppDigitCandidates(): array
    {
        $primary = $this->whatsAppDigits();
        if ($primary === '')
        {
            return [];
        }

        $candidates = [$primary];
        if (preg_match('/^54(?!9)(\d{10,})$/', $primary, $matches) === 1)
        {
            $candidates[] = '549'.$matches[1];
        }
        if (preg_match('/^549(\d{10,})$/', $primary, $matches) === 1)
        {
            $candidates[] = '54'.$matches[1];
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Fetch the WhatsApp profile photo when this team uses the local gateway and none is stored yet.
     */
    public function refreshWhatsAppAvatar(): void
    {
        $candidates = $this->whatsAppDigitCandidates();
        $teamId = (int) ($this->team_id ?? 0);
        if ($candidates === [] || $teamId < 1)
        {
            return;
        }

        $store = app(WhatsAppProfilePhotoStore::class);
        foreach ($candidates as $digits)
        {
            if ($store->isFresh($teamId, $digits))
            {
                return;
            }
        }

        $digits = $candidates[0];
        foreach ($candidates as $candidate)
        {
            if (str_starts_with($candidate, '549'))
            {
                $digits = $candidate;
                break;
            }
        }

        $team = Team::withoutGlobalScopes()->find($teamId);
        $baseUrl = $team?->getWhatsAppServiceBaseUrl() ?? '';
        if ($team === null || ! $team->usesLocalWhatsApp() || $baseUrl === '')
        {
            return;
        }

        $gateway = new LocalWhatsAppGateway(
            $baseUrl,
            config('whatsapp.local.webhook_secret'),
            $teamId,
        );

        $store->hydrateFromGateway($gateway, $teamId, [$digits]);
    }

    /**
     * @return array<string, mixed>
     */
    private function chatAssistantData(): array
    {
        $data = $this->data;
        if (is_array($data))
        {
            return $data;
        }
        if (is_object($data))
        {
            return get_object_vars($data);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataArray(): array
    {
        $decoded = json_decode(json_encode($this->data ?? new \stdClass), true);

        return is_array($decoded) ? $decoded : [];
    }

    public const EMAIL_DOMAIN_MISSING = 'domain_not_found';

    public static function emailFailureIsPermanent(string $reason): bool
    {
        $lower = mb_strtolower($reason);

        foreach ([
            'invalid email',
            'user unknown',
            'unknown user',
            'mailbox not found',
            'does not exist',
            'no such user',
            'recipient address rejected',
            '5.1.1',
            'not exist',
        ] as $needle)
        {
            if (str_contains($lower, $needle))
            {
                return true;
            }
        }

        return false;
    }

    public static function whatsAppFailureIsPermanent(string $reason): bool
    {
        $lower = mb_strtolower($reason);

        return str_contains($lower, 'cannot resolve whatsapp jid')
            || str_contains($lower, 'not registered')
            || str_contains($lower, 'is not on whatsapp');
    }

    /**
     * @return array{channel: string, at: string, status: string, summary: string}|null
     */
    public function lastOutboundMessage(): ?array
    {
        $message = $this->dataArray()['last_message'] ?? null;
        if (! is_array($message))
        {
            return null;
        }

        $channel = (string) ($message['channel'] ?? '');
        $at = (string) ($message['at'] ?? '');
        $status = (string) ($message['status'] ?? '');
        $summary = (string) ($message['summary'] ?? '');
        if ($channel === '' || $at === '')
        {
            return null;
        }

        return [
            'channel' => $channel,
            'at' => $at,
            'status' => $status,
            'summary' => $summary,
        ];
    }

    public function emailDomainOk(): ?bool
    {
        $check = $this->dataArray()['channels']['email'] ?? null;
        if (! is_array($check))
        {
            return null;
        }

        $address = (string) ($check['address'] ?? '');
        if ($address === '' || $address !== trim((string) $this->email))
        {
            return null;
        }

        $domain = (string) ($check['domain'] ?? '');
        if ($domain === 'ok')
        {
            return true;
        }

        if ($domain === 'missing' || (($check['valid'] ?? null) === false && (string) ($check['reason'] ?? '') === self::EMAIL_DOMAIN_MISSING))
        {
            return false;
        }

        return null;
    }

    public function storedChannelValid(string $channel): ?bool
    {
        $check = $this->dataArray()['channels'][$channel] ?? null;
        if (! is_array($check) || ! array_key_exists('valid', $check) || $check['valid'] === null)
        {
            return null;
        }

        $address = (string) ($check['address'] ?? '');
        $current = $channel === 'whatsapp' ? (string) $this->phone : (string) $this->email;
        if ($address === '' || $address !== $current)
        {
            return null;
        }

        return (bool) $check['valid'];
    }

    public function storedChannelLastError(string $channel): ?string
    {
        $check = $this->dataArray()['channels'][$channel] ?? null;
        if (! is_array($check))
        {
            return null;
        }

        $address = (string) ($check['address'] ?? '');
        $current = $channel === 'whatsapp' ? (string) $this->phone : (string) $this->email;
        if ($address === '' || $address !== $current)
        {
            return null;
        }

        $error = $check['last_error']['message'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    public function recordOutboundChannel(string $channel, string $address, ?bool $valid, string $status, string $summary, ?string $reason = null): void
    {
        $data = $this->dataArray();
        $existing = $data['channels'][$channel] ?? null;
        $sameAddress = is_array($existing) && (string) ($existing['address'] ?? '') === $address;
        $nextValid = $valid;
        if ($nextValid === null && $sameAddress && array_key_exists('valid', $existing))
        {
            $nextValid = $existing['valid'];
        }

        $nextReason = $reason;
        if ($nextReason === null && $sameAddress)
        {
            $nextReason = $existing['reason'] ?? null;
        }

        $lastError = $sameAddress && is_array($existing['last_error'] ?? null) ? $existing['last_error'] : null;
        if ($nextValid === true)
        {
            $nextReason = null;
            $lastError = null;
        } elseif ($status === 'failed')
        {
            $text = trim((string) ($reason !== null && $reason !== '' ? $reason : $summary));
            if ($text !== '')
            {
                $lastError = [
                    'message' => mb_substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 160),
                    'at' => now()->toIso8601String(),
                ];
            }
        }

        $data['channels'][$channel] = [
            'address' => $address,
            'valid' => $nextValid,
            'checked_at' => now()->toIso8601String(),
            'reason' => $nextReason,
            'last_error' => $lastError,
        ];
        $data['last_message'] = [
            'channel' => $channel,
            'at' => now()->toIso8601String(),
            'status' => $status,
            'summary' => mb_substr(trim(preg_replace('/\s+/', ' ', $summary) ?? ''), 0, 160),
        ];

        $this->data = $data;
        if ($this->exists)
        {
            $this->save();
        }
    }

    /**
     * A missing domain stays out of later sends. A domain that exists only clears that mark.
     */
    public function applyEmailDomainCheck(bool $domainExists): void
    {
        $email = trim((string) $this->email);
        if ($email === '')
        {
            return;
        }

        $data = $this->dataArray();
        $existing = is_array($data['channels']['email'] ?? null) ? $data['channels']['email'] : [];
        $reason = (string) ($existing['reason'] ?? '');

        if ($domainExists)
        {
            $existing['address'] = $email;
            $existing['domain'] = 'ok';
            $existing['checked_at'] = now()->toIso8601String();
            if ($reason === self::EMAIL_DOMAIN_MISSING)
            {
                $existing['valid'] = null;
                $existing['reason'] = null;
                $existing['last_error'] = null;
            }
            $data['channels']['email'] = $existing;
        } elseif ($reason !== '' && $reason !== self::EMAIL_DOMAIN_MISSING)
        {
            $existing['address'] = $email;
            $existing['domain'] = 'missing';
            $existing['checked_at'] = now()->toIso8601String();
            $data['channels']['email'] = $existing;
        } else
        {
            $data['channels']['email'] = [
                'address' => $email,
                'valid' => false,
                'domain' => 'missing',
                'checked_at' => now()->toIso8601String(),
                'reason' => self::EMAIL_DOMAIN_MISSING,
                'last_error' => [
                    'message' => 'No tiene registro MX',
                    'at' => now()->toIso8601String(),
                ],
            ];
        }

        $this->data = $data;
        if ($this->exists)
        {
            $this->save();
        }
    }

    public function forgetChannelCheck(string $channel): void
    {
        $data = $this->dataArray();
        if (! isset($data['channels'][$channel]))
        {
            return;
        }

        unset($data['channels'][$channel]);
        $this->data = $data;
    }

    /**
     * Scope to exclude collaborators removed from a specific project
     */
    public function scopeExcludeRemovedFromProject($query, $projectId)
    {
        return $query->whereNotIn('id', function ($subQuery) use ($projectId)
        {
            $subQuery
                ->select('contact_id')
                ->from('contact_project')
                ->where('project_id', $projectId)
                ->whereNotNull('deleted_at');
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
        if ($this->relationLoaded('language'))
        {
            $languageRelation = $this->getRelation('language');
            if ($languageRelation)
            {
                return $languageRelation->name;
            }
        }

        if (isset($this->attributes['language']))
        {
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
        if ($this->relationLoaded('language'))
        {
            $languageRelation = $this->getRelation('language');
            if ($languageRelation)
            {
                return $languageRelation->flag;
            }
        }

        if (isset($this->attributes['language']))
        {
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
        \Log::info('Getting formatted language pairs for contact ID: '.$this->id);
        \Log::info('Language variants count: '.$this->languageVariants->count());

        $pairs = $this->languageVariants->map(function ($variant)
        {
            \Log::info('Processing variant: '.$variant->id.' - '.$variant->source_language_code.' -> '.$variant->target_language_code);

            $sourceLanguage = $variant->sourceLanguage;
            $targetLanguage = $variant->targetLanguage;

            \Log::info('Source language: '.($sourceLanguage ? $sourceLanguage->name : 'null'));
            \Log::info('Target language: '.($targetLanguage ? $targetLanguage->name : 'null'));

            return [
                'source_language' => $variant->source_language_code,
                'target_language' => $variant->target_language_code,
                'source_language_text' => $sourceLanguage ? $sourceLanguage->name : $variant->source_language_code,
                'target_language_text' => $targetLanguage ? $targetLanguage->name : $variant->target_language_code,
                'is_native' => $variant->is_certified,
            ];
        });

        \Log::info('Formatted pairs: '.json_encode($pairs));

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
        if ($this->status)
        {
            return '<span class="badge rounded-pill '.$this->status->label_class.'">'.$this->status->name.'</span>';
        }

        return '<span class="badge rounded-pill bg-label-secondary">Unknown</span>';
    }

    /**
     * Get total count of active collaborators (contacts linked to users with collaborator role)
     */
    public static function getTotalCollaborators($teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        return static::where('team_id', $teamId)
            ->whereHas('user', function ($query)
            {
                $query->whereHas('roles', function ($q)
                {
                    $q->where('name', 'collaborator');
                });
            })
            ->count();
    }

    /**
     * Get count of collaborators created this month
     */
    public static function getNewCollaboratorsThisMonth($teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        return static::where('team_id', $teamId)
            ->whereHas('user', function ($query)
            {
                $query->whereHas('roles', function ($q)
                {
                    $q->where('name', 'collaborator');
                });
            })
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }

    /**
     * Get count of contacts pending acceptance (not linked to any user)
     */
    public static function getPendingAcceptanceCount($teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        return static::where('team_id', $teamId)
            ->whereNull('user_id')
            ->count();
    }

    /**
     * Get count of new collaborators created in the last week and linked to a user
     */
    public static function getNewCollaboratorsThisWeek($teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        return static::where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subWeek())
            ->count();
    }

    /**
     * Get count of contacts not updated in the last 6 months
     */
    public static function getNotUpdatedInSixMonths($teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        return static::where('team_id', $teamId)
            ->where('updated_at', '<=', now()->subMonths(6))
            ->count();
    }

    /**
     * Get collaborators with incomplete data
     */
    public static function getIncompleteCollaborators($limit = 20, $teamId = null)
    {
        $teamId = $teamId ?? (auth()->check() ? auth()->user()->currentTeam->id : 1);

        return static::where('team_id', $teamId)
            ->where(function ($query)
            {
                $query
                    ->whereNull('email')
                    ->orWhere('email', '')
                    ->orWhereNull('phone')
                    ->orWhere('phone', '');
            })
            ->with(['language', 'fares', 'softwares'])
            ->limit($limit)
            ->get()
            ->map(function ($contact)
            {
                $missingFields = [];
                $missingCount = 0;

                // Check required fields
                if (empty($contact->email))
                {
                    $missingFields[] = 'email';
                    $missingCount++;
                }

                if (empty($contact->phone))
                {
                    $missingFields[] = 'teléfono';
                    $missingCount++;
                }

                // Check optional but important fields
                if (empty($contact->language))
                {
                    $missingFields[] = 'idioma';
                    $missingCount++;
                }

                if (empty($contact->profile))
                {
                    $missingFields[] = 'perfil';
                    $missingCount++;
                }

                if (empty($contact->birthday))
                {
                    $missingFields[] = 'cumpleaños';
                    $missingCount++;
                }

                // Check related data
                if ($contact->fares->count() === 0)
                {
                    $missingFields[] = 'servicios';
                    $missingCount++;
                }

                if ($contact->softwares->count() === 0)
                {
                    $missingFields[] = 'software';
                    $missingCount++;
                }

                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'avatar' => \App\Helpers\AvatarHelper::generate($contact->name, 80),
                    'missing_count' => $missingCount,
                    'missing_fields' => $missingFields,
                    'missing_text' => $missingCount > 0
                        ? (
                            $missingCount === 1
                                ? 'Falta: '.implode(', ', $missingFields)
                                : "{$missingCount} campos por completar"
                        )
                        : 'Datos completos',
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
            ->map(function ($group)
            {
                return $group->count();
            });

        $totalContacts = $contactStats->sum();

        $data = ['totalContacts' => $totalContacts];
        foreach ($statusLabels as $statusId => $label)
        {
            $count = $contactStats[$statusId] ?? 0;
            $percentage = $totalContacts > 0 ? round(($count / $totalContacts) * 100, 2) : 0;
            $data["total$label"] = $count;
            $data[lcfirst($label).'Percentage'] = $percentage;
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

        if (! $latestAction)
        {
            return 0;
        }

        $startTime = Carbon::parse($latestAction->start_time);
        $endTime = Carbon::now();

        // Ensure we always return a positive value and round to avoid decimals
        $seconds = abs($endTime->diffInSeconds($startTime, false));

        return max(0, (int) round($seconds));
    }

    public function calculateTotalAccumulatedSeconds()
    {
        $completedActions = UserContactAction::where('contact_id', $this->id)
            ->whereNotNull('end_time')
            ->get();

        $totalSeconds = 0;

        foreach ($completedActions as $action)
        {
            $startTime = Carbon::parse($action->start_time);
            $endTime = Carbon::parse($action->end_time);

            // Only add positive time differences
            if ($endTime->greaterThanOrEqualTo($startTime))
            {
                $totalSeconds += abs($endTime->diffInSeconds($startTime, false));
            }
        }

        $currentActionSeconds = $this->calculateCurrentActionSeconds();
        $totalSeconds += $currentActionSeconds;

        // Ensure we never return a negative value and round to avoid decimals
        return max(0, (int) round($totalSeconds));
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
        return $this
            ->belongsToMany(Enterprise::class, 'contact_enterprise')
            ->withPivot('position', 'department_id', 'superior_id')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'contact_category');
    }

    public function softwares(): BelongsToMany
    {
        return $this
            ->belongsToMany(Software::class, 'contact_softwares')
            ->withPivot('proficiency_level', 'notes')
            ->withTimestamps();
    }

    public function fares(): BelongsToMany
    {
        return $this
            ->belongsToMany(Fare::class, 'contact_fare')
            ->withPivot('price', 'unit_id', 'currency_code', 'source_language_code', 'target_language_code')
            ->withTimestamps();
    }

    public function topics(): BelongsToMany
    {
        return $this
            ->belongsToMany(Topic::class, 'contact_topics')
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this
            ->belongsToMany(Project::class, 'contact_project')
            ->using(ContactProject::class)
            ->withPivot('message_sent', 'status', 'sent_at', 'viewed_at', 'responded_at', 'response_message', 'deleted_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');  // Only get non-deleted relationships
    }

    public function list60(): HasOne
    {
        return $this->hasOne(List60::class);
    }

    public function isInList60(): bool
    {
        return $this->list60()->exists();
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function contactInteractions(): HasMany
    {
        return $this->hasMany(ContactInteraction::class)->orderByDesc('occurred_at');
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
     * Get contact's astral profile
     */
    public function astralProfile()
    {
        return $this->hasOne(ContactAstralProfile::class);
    }

    /**
     * Get the WhatsApp formatted phone number from the contact
     *
     * @return string|null
     */
    public function getWhatsAppNumber()
    {
        // First try to get phone from direct field
        if ($this->phone)
        {
            $cleanNumber = preg_replace('/[^0-9]/', '', (string) $this->phone);

            return 'whatsapp:+'.$cleanNumber;
        }

        // If no direct phone, try to get from related user
        $relatedUser = $this->user()->first();

        if ($relatedUser && $relatedUser->phone)
        {
            $cleanNumber = preg_replace('/[^0-9]/', '', (string) $relatedUser->phone);

            return 'whatsapp:+'.$cleanNumber;
        }

        return null;
    }

    /**
     * URL to open the team mail UI with compose sidebar prefilled for this contact.
     */
    public function mailComposeListUrl(): ?string
    {
        $team = $this->team;
        if ($team !== null && ! $team->hasModule('mailbox'))
        {
            return null;
        }

        $email = $this->email;
        if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            return null;
        }

        $name = trim($this->name.' '.(string) ($this->surname ?? ''));

        return route('mail-list', array_filter([
            'compose' => '1',
            'to' => $email,
            'name' => $name !== '' ? $name : null,
            'contact_id' => $this->id,
        ]));
    }

    /**
     * URL to open WhatsApp chat for this contact when a number is available.
     */
    public function chatIndexUrl(): ?string
    {
        $wa = $this->getWhatsAppNumber();

        return $wa ? route('chat.index', ['phone' => $wa]) : null;
    }

    public function setPhoneAttribute($value)
    {
        if ($value === null || $value === '')
        {
            $this->attributes['phone'] = null;

            return;
        }

        $cleanNumber = preg_replace('/[^0-9]/', '', (string) $value);
        $this->attributes['phone'] = $cleanNumber !== '' ? $cleanNumber : null;
    }

    /**
     * Configure activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'surname', 'email', 'phone', 'source_id', 'country', 'language', 'responsible_id', 'status_id', 'valoration_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName)
            {
                return match ($eventName)
                {
                    'created' => 'Colaborador creado',
                    'updated' => 'Colaborador actualizado',
                    'deleted' => 'Colaborador eliminado',
                    default => $eventName
                };
            });
    }

    public function currentEnterprise()
    {
        return $this->belongsTo(\App\Models\Enterprise::class, 'current_enterprise_id');
    }

    /**
     * Get the last project for this collaborator
     */
    public function getLastProjectAttribute()
    {
        return $this
            ->projects()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('documents')
            ->useDisk('public')
            ->acceptsMimeTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation']);

        $this
            ->addMediaCollection('media')
            ->useDisk('public')
            ->acceptsMimeTypes([
                // Images
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
                'image/bmp',
                'image/tiff',
                // Videos
                'video/mp4',
                'video/avi',
                'video/mov',
                'video/wmv',
                'video/flv',
                'video/webm',
                'video/mkv',
                // Audio
                'audio/mpeg',
                'audio/mp3',
                'audio/wav',
                'audio/ogg',
                'audio/aac',
                'audio/flac',
                // Documents
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ]);
    }
}
