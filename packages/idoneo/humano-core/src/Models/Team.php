<?php

namespace Idoneo\HumanoCore\Models;

use App\Traits\HasEmailLimits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    use HasEmailLimits, HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
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

    public function getSetting($key, $default = null)
    {
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

        $setting->fill([
            'type' => $options['type'],
            'group' => $options['group'],
            'is_encrypted' => $options['is_encrypted'],
        ]);

        if (! $setting->exists)
        {
            $setting->save();
        }

        $setting->value = $value;

        return $setting->save();
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
}
