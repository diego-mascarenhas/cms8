<?php

namespace App\Models;

use App\Traits\HasEmailLimits;
use App\Traits\HasProspectLimits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    use Billable, HasEmailLimits, HasFactory, HasProspectLimits;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * Get the team that owns the subscription (for Cashier)
     */
    public function stripeEmail()
    {
        return $this->owner->email ?? null;
    }

    /**
     * Get all of the subscriptions for the team.
     */
    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Subscription::class, 'team_id')->orderByDesc('created_at');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'user_id',
        'stripe_id',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    public function settings()
    {
        return $this->hasMany(TeamSetting::class);
    }

    public function externalAccounts()
    {
        return $this->hasMany(ExternalAccount::class);
    }

    public function teamSocialConnections()
    {
        return $this->hasMany(TeamSocialConnection::class);
    }

    /**
     * Team-scoped files (documents, brand assets) stored in team_files with Spatie media.
     */
    public function teamFiles()
    {
        return $this->hasMany(TeamFile::class);
    }

    public function billingAffiliateCommissionsAsReferrer()
    {
        return $this->hasMany(BillingAffiliateCommission::class, 'referrer_team_id');
    }

    public function billingAffiliateCommissionsAsPayer()
    {
        return $this->hasMany(BillingAffiliateCommission::class, 'paying_team_id');
    }

    /**
     * Find a team by any of its Stripe customer IDs (main stripe_id or per-category settings).
     */
    public static function findByStripeCustomerId(string $stripeCustomerId): ?self
    {
        $team = static::where('stripe_id', $stripeCustomerId)->first();
        if ($team)
        {
            return $team;
        }

        $setting = TeamSetting::where('key', 'like', 'stripe_id_%')
            ->where('value', $stripeCustomerId)
            ->first();

        return $setting ? $setting->team : null;
    }

    /**
     * Get the mailboxes for the team.
     */
    public function mailboxes()
    {
        return $this->hasMany(Mailbox::class);
    }

    public function getSetting($key, $default = null)
    {
        // If settings are already loaded, use them to avoid N+1 queries
        if ($this->relationLoaded('settings'))
        {
            $setting = $this->settings->firstWhere('key', $key);

            return $setting ? $setting->value : $default;
        }

        // Otherwise, query the database
        return $this->settings()->where('key', $key)->first()?->value ?? $default;
    }

    public function setSetting($key, $value, $options = [])
    {
        $defaultOptions = [
            'type' => 'string',
            'group' => 'general',
            'is_encrypted' => false,
        ];

        $options = array_merge($defaultOptions, $options);

        $setting = $this->settings()->firstOrNew(['key' => $key]);

        if (! $setting->exists)
        {
            $this->syncTeamSettingsSequenceIfNeeded();
        }

        $setting->fill([
            'type' => $options['type'],
            'group' => $options['group'],
            'is_encrypted' => $options['is_encrypted'],
        ]);

        $setting->value = $value;

        return $setting->save();
    }

    private function syncTeamSettingsSequenceIfNeeded(): void
    {
        if (DB::getDriverName() !== 'pgsql')
        {
            return;
        }

        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('team_settings', 'id'),
                COALESCE((SELECT MAX(id) FROM team_settings), 0) + 1,
                false
            )
        ");
    }

    public function hasPasswordsMasterKey(): bool
    {
        return filled($this->getSetting('passwords_master_key_hash'));
    }

    public function verifyPasswordsMasterKey(string $plainMasterKey): bool
    {
        $hash = $this->getSetting('passwords_master_key_hash');
        if (! is_string($hash) || $hash === '')
        {
            return false;
        }

        return Hash::check($plainMasterKey, $hash);
    }

    /**
     * Get the contacts for this team.
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class, 'team_id');
    }

    /**
     * Get the message deliveries for this team.
     */
    public function messageDeliveries()
    {
        return $this->hasManyThrough(
            \App\Models\MessageDelivery::class,
            \App\Models\Message::class,
            'team_id', // Foreign key on messages table
            'message_id', // Foreign key on message_deliveries table
            'id', // Local key on teams table
            'id', // Local key on messages table
        );
    }

    public function paymentAccounts()
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * Get the modules enabled for this team.
     */
    public function modules()
    {
        return $this->belongsToMany(Module::class)
            ->withPivot('settings', 'status')
            ->withTimestamps();
    }

    /**
     * Check if a specific module is active for this team.
     */
    public function hasModule($moduleKey)
    {
        // Use eager loaded modules if available to avoid N+1 queries
        if ($this->relationLoaded('modules'))
        {
            return $this->modules
                ->where('key', $moduleKey)
                ->where('pivot.status', 1)
                ->isNotEmpty();
        }

        // Fallback to query if modules not loaded
        return $this->modules()
            ->where('key', $moduleKey)
            ->where('module_team.status', 1)
            ->exists();
    }

    /**
     * Enable a module for this team.
     */
    public function enableModule($moduleKey, $settings = null)
    {
        $module = Module::where('key', $moduleKey)->first();

        if (! $module)
        {
            return false;
        }

        $existingPivot = $this->modules()
            ->where('modules.id', $module->id)
            ->first();

        if ($existingPivot)
        {
            $this->modules()->updateExistingPivot($module->id, [
                'status' => 1,
                'settings' => $settings ? json_encode($settings) : $existingPivot->pivot->settings,
            ]);
        } else
        {
            $this->modules()->attach($module->id, [
                'status' => 1,
                'settings' => $settings ? json_encode($settings) : null,
            ]);
        }

        if ($moduleKey === 'performance_insights')
        {
            $this->load('modules');
            \App\Support\TeamDefaultShortcuts::applyPerformanceInsightsTeamDefaults($this);
        }

        return true;
    }

    /**
     * Disable a module for this team.
     */
    public function disableModule($moduleKey)
    {
        $module = Module::where('key', $moduleKey)->first();

        if (! $module)
        {
            return false;
        }

        $this->modules()->updateExistingPivot($module->id, [
            'status' => 0,
        ]);

        return true;
    }

    /**
     * Generate a secure hash for the team ID (same as TeamAssetRepository)
     */
    public function getTeamHash($teamId = null)
    {
        $teamId = $teamId ?? $this->id;

        return static::generateTeamHash($teamId);
    }

    /**
     * Generate a secure hash for any team ID (static version)
     */
    public static function generateTeamHash($teamId)
    {
        return substr(md5('team_salt_'.$teamId.'_'.config('app.key')), 0, 12);
    }

    /**
     * Get Twilio configuration for this team.
     */
    public function getTwilioConfig()
    {
        return [
            'sid' => $this->getSetting('twilio_sid'),
            'token' => $this->getSetting('twilio_token'),
            'sms_from' => $this->getSetting('twilio_sms_from'),
            'whatsapp_from' => $this->getSetting('twilio_whatsapp_from'),
            'webhook_url' => $this->getTwilioWebhookUrl(),
            'status_callback_url' => $this->getTwilioStatusCallbackUrl(),
        ];
    }

    /**
     * Get the webhook URL for this team.
     */
    public function getTwilioWebhookUrl()
    {
        $customUrl = $this->getSetting('twilio_webhook_url');

        if (! empty($customUrl))
        {
            return $customUrl;
        }

        // Generate team-specific webhook URL using deterministic hash
        $hash = $this->getTeamHash();

        return url("/twilio/webhook/{$hash}");
    }

    /**
     * Get the status callback URL for this team.
     */
    public function getTwilioStatusCallbackUrl()
    {
        $customUrl = $this->getSetting('twilio_status_callback_url');

        if (! empty($customUrl))
        {
            return $customUrl;
        }

        // Generate team-specific status callback URL using deterministic hash
        $hash = $this->getTeamHash();

        return url("/twilio/status/{$hash}");
    }

    /**
     * Get the WhatsApp Assistant number for this team (driver-agnostic).
     * Uses whatsapp_from first, then falls back to twilio_whatsapp_from.
     */
    public function getWhatsAppFrom(): ?string
    {
        return $this->getSetting('whatsapp_from') ?: $this->getSetting('twilio_whatsapp_from');
    }

    /**
     * @return array<string, mixed>
     */
    public function getDecodedBusinessConfig(): array
    {
        $saved = $this->getSetting('business_config', []);
        if (is_string($saved))
        {
            $saved = json_decode($saved, true) ?: [];
        }

        return is_array($saved) ? $saved : [];
    }

    public function isPublicCatalogEnabled(): bool
    {
        return filter_var($this->getSetting('public_catalog_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether daily performance insights create an in-app notification (navbar bell).
     */
    public function performanceInsightsInAppNotificationEnabled(): bool
    {
        if (! $this->hasModule('performance_insights'))
        {
            return false;
        }

        return filter_var(
            $this->getSetting('performance_insights_in_app_notification', true),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * When the HTTP webhook has no resolved team ($team is null), infer receiving team from
     * {@see findByWhatsAppNumber} (team_settings: whatsapp_from / twilio_whatsapp_from).
     * Local Baileys webhooks normally send team_id so $team is set.
     *
     * @param  string  $cleanToDigits  Digits-only destination number from the webhook.
     */
    public static function resolveInboundWebhookTeamId(?int $routeTeamId, string $cleanToDigits): ?int
    {
        if ($routeTeamId !== null && $routeTeamId > 0)
        {
            return $routeTeamId;
        }
        if ($cleanToDigits === '' || strlen($cleanToDigits) < 8)
        {
            return null;
        }
        if (Schema::hasTable('team_settings'))
        {
            $byNumber = static::findByWhatsAppNumber($cleanToDigits);
            if ($byNumber !== null)
            {
                return (int) $byNumber->id;
            }
        }

        return null;
    }

    /**
     * Normalize wizard "business_website" (or route segment) to hostname only: lowercase, no scheme, path, or trailing slash.
     */
    public static function normalizePublicShopDomain(?string $website): ?string
    {
        if ($website === null)
        {
            return null;
        }

        $website = trim($website);
        if ($website === '')
        {
            return null;
        }

        $toParse = $website;
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $toParse))
        {
            $toParse = 'https://'.ltrim($toParse, '/');
        }

        $parts = parse_url($toParse);
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';

        if ($host !== '')
        {
            return $host;
        }

        if (isset($parts['path']))
        {
            $segment = strtolower(trim((string) $parts['path'], '/'));
            $segment = explode('/', $segment)[0] ?? '';

            if ($segment !== '' && preg_match('/^[a-z0-9.-]+$/i', $segment) === 1)
            {
                return $segment;
            }
        }

        return null;
    }

    /**
     * Host used in /shop/{host} from business_config.business_website.
     */
    public function getPublicCatalogShopDomain(): ?string
    {
        $website = trim((string) ($this->getDecodedBusinessConfig()['business_website'] ?? ''));

        return static::normalizePublicShopDomain($website);
    }

    /**
     * URL segment from business_config.business_name (fallback: team name) for /shop/{slug}.
     */
    public function getPublicCatalogNameSlug(): ?string
    {
        $config = $this->getDecodedBusinessConfig();
        $name = trim((string) ($config['business_name'] ?? ''));
        if ($name === '')
        {
            $name = trim((string) $this->name);
        }
        if ($name === '')
        {
            return null;
        }

        $slug = Str::slug($name);

        return $slug !== '' ? $slug : null;
    }

    public function publicCatalogShopUrl(): ?string
    {
        $domain = $this->getPublicCatalogShopDomain();
        if ($domain !== null)
        {
            return url('/shop/'.$domain);
        }

        $nameSlug = $this->getPublicCatalogNameSlug();
        if ($nameSlug !== null)
        {
            return url('/shop/'.$nameSlug);
        }

        return null;
    }

    /**
     * Resolve team for public catalog: match business website host first, then slug of business/team name.
     * When multiple teams share the same name slug, returns null (ambiguous).
     */
    public static function findForPublicCatalog(string $slug): ?self
    {
        $enabledTeams = [];
        foreach (TeamSetting::query()->where('key', 'public_catalog_enabled')->get() as $row)
        {
            if (! filter_var($row->value, FILTER_VALIDATE_BOOLEAN))
            {
                continue;
            }
            $team = $row->team;
            if ($team)
            {
                $enabledTeams[] = $team;
            }
        }

        $requestedDomain = static::normalizePublicShopDomain($slug);
        if ($requestedDomain !== null && $requestedDomain !== '')
        {
            foreach ($enabledTeams as $team)
            {
                $domain = $team->getPublicCatalogShopDomain();
                if ($domain !== null && hash_equals($domain, $requestedDomain))
                {
                    return $team;
                }
            }
        }

        $requestedNameSlug = Str::slug($slug);
        if ($requestedNameSlug === '')
        {
            return null;
        }

        $nameMatches = [];
        foreach ($enabledTeams as $team)
        {
            $nameSlug = $team->getPublicCatalogNameSlug();
            if ($nameSlug !== null && hash_equals($nameSlug, $requestedNameSlug))
            {
                $nameMatches[] = $team;
            }
        }

        if (count($nameMatches) === 1)
        {
            return $nameMatches[0];
        }

        return null;
    }

    /**
     * Digits-only WhatsApp for checkout links.
     */
    public function catalogCheckoutWhatsAppDigits(): ?string
    {
        $raw = $this->getWhatsAppFrom();
        if ($raw === null || $raw === '')
        {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $raw);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Base URL of the Node WhatsApp service for this team (one instance per team = one number per team, no disconnects).
     * If set (whatsapp_service_url), use it; otherwise use the global config.
     */
    public function getWhatsAppServiceBaseUrl(): string
    {
        $url = $this->getSetting('whatsapp_service_url');
        if ($url !== null && $url !== '')
        {
            return rtrim((string) $url, '/');
        }

        return rtrim(config('whatsapp.local.base_url', ''), '/');
    }

    /**
     * Ensure this WhatsApp number is only linked to the given team.
     * Removes whatsapp_from/twilio_whatsapp_from with this value from any other team.
     */
    public static function ensureOnlyTeamHasWhatsAppNumber(int $teamId, string $normalizedNumber): void
    {
        $normalizedNumber = preg_replace('/[^0-9]/', '', $normalizedNumber);
        if ($normalizedNumber === '')
        {
            return;
        }

        TeamSetting::whereIn('key', ['whatsapp_from', 'twilio_whatsapp_from'])
            ->where('value', $normalizedNumber)
            ->where('team_id', '!=', $teamId)
            ->delete();
    }

    /**
     * Find a team by its WhatsApp Assistant number (normalized digits only).
     */
    public static function findByWhatsAppNumber(string $normalizedNumber): ?self
    {
        $normalizedNumber = preg_replace('/[^0-9]/', '', $normalizedNumber);
        if ($normalizedNumber === '')
        {
            return null;
        }

        $settings = TeamSetting::whereIn('key', ['whatsapp_from', 'twilio_whatsapp_from'])
            ->whereNotNull('value')
            ->get();

        foreach ($settings as $setting)
        {
            $value = $setting->value;
            if (is_string($value) && preg_replace('/[^0-9]/', '', $value) === $normalizedNumber)
            {
                return $setting->team;
            }
        }

        return null;
    }

    /** Token expiry minutes for WhatsApp link. */
    public static function whatsAppLinkTokenExpiryMinutes(): int
    {
        return 15;
    }

    /**
     * Generate a signed token for linking a WhatsApp number to this team (expires in 15 minutes).
     */
    public function generateWhatsAppLinkToken(): string
    {
        return encrypt([
            'team_id' => $this->id,
            'exp' => now()->addMinutes(static::whatsAppLinkTokenExpiryMinutes())->timestamp,
        ]);
    }

    /**
     * Parse and validate a WhatsApp link token; returns the team or null if invalid/expired.
     */
    public static function fromWhatsAppLinkToken(string $token): ?self
    {
        try
        {
            $payload = decrypt($token);
            if (! is_array($payload) || empty($payload['team_id']) || empty($payload['exp']))
            {
                return null;
            }
            if ((int) $payload['exp'] < time())
            {
                return null;
            }

            return static::find($payload['team_id']);
        } catch (\Throwable)
        {
            return null;
        }
    }

    /**
     * Find a team by webhook hash.
     */
    public static function findByWebhookHash($hash)
    {
        // Since the hash is deterministic, we need to check all teams
        // In practice, this is efficient because most apps don't have thousands of teams
        return static::get()->first(function ($team) use ($hash)
        {
            return static::generateTeamHash($team->id) === $hash;
        });
    }

    /**
     * Check if Twilio is configured for this team.
     */
    public function hasTwilioConfig()
    {
        return ! empty($this->getSetting('twilio_sid')) && ! empty($this->getSetting('twilio_token'));
    }

    /**
     * Get outgoing email configuration for this team (with fallbacks to .env).
     */
    public function getOutgoingEmailConfig()
    {
        return [
            'host' => $this->getSetting('mail_host', env('MAIL_HOST')),
            'port' => $this->getSetting('mail_port', env('MAIL_PORT')),
            'username' => $this->getSetting('mail_username', env('MAIL_USERNAME')),
            'password' => $this->getSetting('mail_password', env('MAIL_PASSWORD')),
            'encryption' => $this->getSetting('mail_encryption', env('MAIL_ENCRYPTION')),
            'from_address' => $this->getSetting('mail_from_address', env('MAIL_FROM_ADDRESS')),
            'from_name' => $this->getSetting('mail_from_name', env('MAIL_FROM_NAME')),
        ];
    }

    /**
     * Get incoming email configuration for this team.
     */
    public function getIncomingEmailConfig()
    {
        return [
            'host' => $this->getSetting('imap_host'),
            'port' => $this->getSetting('imap_port', '993'),
            'username' => $this->getSetting('imap_username'),
            'password' => $this->getSetting('imap_password'),
            'encryption' => $this->getSetting('imap_encryption', 'ssl'),
        ];
    }

    /**
     * Check if outgoing email is configured for this team.
     */
    public function hasOutgoingEmailConfig()
    {
        // Check only team settings, not fallbacks to env
        $host = $this->getSetting('mail_host');
        $username = $this->getSetting('mail_username');

        return ! empty($host) && ! empty($username);
    }

    /**
     * Check if this team is using the system's SMTP (should show advertising).
     */
    public function isUsingSystemSmtp()
    {
        return ! $this->hasOutgoingEmailConfig();
    }

    /**
     * Get the advertising footer HTML for teams using system SMTP.
     */
    public function getAdvertisingFooter()
    {
        if (! $this->isUsingSystemSmtp())
        {
            return '';
        }

        return '
		<div style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-top: 1px solid #e9ecef; text-align: center; font-family: Arial, sans-serif;">
			<p style="margin: 0; color: #6c757d; font-size: 12px;">
				Este email fue enviado con
				<a href="https://revisionalpha.com/emailer" style="color: #007bff; text-decoration: none; font-weight: bold;">REVISION ALPHA Mailer</a>
			</p>
			<p style="margin: 5px 0 0 0; color: #6c757d; font-size: 11px;">
				Email Marketing fácil, rápido y seguro -
				<a href="https://revisionalpha.com/emailer" style="color: #007bff; text-decoration: none;">¡Empieza ahora!</a>
			</p>
		</div>';
    }

    /**
     * Check if incoming email is configured for this team.
     */
    public function hasIncomingEmailConfig()
    {
        return ! empty($this->getSetting('imap_host')) && ! empty($this->getSetting('imap_username'));
    }

    /**
     * Whether the team has saved a minimal business profile (wizard at team business config).
     */
    public function hasCompletedBusinessConfiguration(): bool
    {
        $config = $this->getSetting('business_config', []);
        if (! is_array($config))
        {
            return false;
        }

        return trim((string) ($config['business_name'] ?? '')) !== '';
    }

    /**
     * Stripe price IDs linked to the configured registration product (recurring checkout).
     *
     * @return Collection<int, string>
     */
    public static function registrationCheckoutStripePriceIds(): Collection
    {
        $productId = trim((string) config('registration.stripe_product_id', ''));

        if ($productId === '')
        {
            return collect();
        }

        return SubscriptionProduct::query()
            ->where(function ($query) use ($productId): void
            {
                $query->where('stripe_product', $productId)
                    ->orWhere('stripe_id', $productId);
            })
            ->pluck('stripe_price')
            ->filter(fn ($priceId): bool => is_string($priceId) && str_starts_with($priceId, 'price_'))
            ->unique()
            ->values();
    }

    /**
     * Whether the current team satisfies registration billing (active subscription for the registration product prices).
     */
    public function passesRegistrationBillingGate(): bool
    {
        $demoTeamIds = config('registration.demo_team_ids', []);

        if ($demoTeamIds !== [] && in_array((int) $this->id, $demoTeamIds, true))
        {
            return true;
        }

        $priceIds = static::registrationCheckoutStripePriceIds();

        foreach ($this->subscriptions()->get() as $subscription)
        {
            if (! $subscription->active())
            {
                continue;
            }

            if ($priceIds->isNotEmpty() && $priceIds->contains($subscription->stripe_price))
            {
                return true;
            }

            $data = $this->subscriptionGateMetadataAsArray($subscription->data);

            if (($data['registration_checkout'] ?? null) === '1' || ($data['registration_checkout'] ?? null) === 1)
            {
                return true;
            }

            if (($data['payment_link_signup'] ?? null) === '1' || ($data['payment_link_signup'] ?? null) === 1)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionGateMetadataAsArray(mixed $data): array
    {
        if (is_array($data))
        {
            return $data;
        }

        if (is_string($data) && $data !== '')
        {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    // Backwards compatibility methods (deprecated)

    /**
     * @deprecated Use getOutgoingEmailConfig() instead
     */
    public function getEmailConfig()
    {
        return $this->getOutgoingEmailConfig();
    }

    /**
     * @deprecated Use getIncomingEmailConfig() instead
     */
    public function getImapConfig()
    {
        return $this->getIncomingEmailConfig();
    }

    /**
     * @deprecated Use hasOutgoingEmailConfig() instead
     */
    public function hasEmailConfig()
    {
        return $this->hasOutgoingEmailConfig();
    }

    /**
     * @deprecated Use hasIncomingEmailConfig() instead
     */
    public function hasImapConfig()
    {
        return $this->hasIncomingEmailConfig();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(Jetstream::userModel(), Jetstream::membershipModel())
            ->withPivot(['role'])
            ->withTimestamps()
            ->as('membership');
    }
}
