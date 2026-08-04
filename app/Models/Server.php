<?php

namespace App\Models;

use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    protected $table = 'servers';

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    protected $fillable = [
        'name',
        'ip',
        'server_url',
        'username',
        'operating_system',
        'control_panel',
        'encrypted_token',
        'team_id',
        'success',
        'status_id',
        'data',
    ];

    protected $casts = [
        'success' => 'boolean',
        'status_id' => ServerStatus::class,
        'data' => 'array',
    ];

    public static function normalizeHostname(?string $value): ?string
    {
        if ($value === null)
        {
            return null;
        }

        $value = trim($value);

        if ($value === '')
        {
            return '';
        }

        $value = preg_replace('#^[a-z][a-z0-9+\-.]*://#i', '', $value) ?? $value;
        $value = preg_replace('#^[a-z][a-z0-9+\-.]*//#i', '', $value) ?? $value;
        $value = rtrim($value, '/');

        if (str_contains($value, '/'))
        {
            $value = explode('/', $value, 2)[0];
        }

        return $value;
    }

    public function setServerUrlAttribute(?string $value): void
    {
        $this->attributes['server_url'] = self::normalizeHostname($value);
    }

    public function getHostnameAttribute(): ?string
    {
        return self::normalizeHostname($this->attributes['server_url'] ?? null);
    }

    // Encrypt token when saving
    public function setEncryptedTokenAttribute($value)
    {
        if (! empty($value))
        {
            $this->attributes['encrypted_token'] = encrypt($value);
        } else
        {
            $this->attributes['encrypted_token'] = null;
        }
    }

    // Decrypt token when accessing
    public function getEncryptedTokenAttribute($value)
    {
        if (! empty($value))
        {
            try
            {
                return decrypt($value);
            } catch (\Exception $e)
            {
                // If decryption fails, return the original value
                return $value;
            }
        }

        return null;
    }

    // Get raw encrypted token (for when you need the encrypted version)
    public function getRawEncryptedToken()
    {
        return $this->attributes['encrypted_token'] ?? null;
    }

    public function getWebIpAttribute()
    {
        return $this->data['web_ip'] ?? null;
    }

    public function getMailIpAttribute()
    {
        return $this->data['mail_ip'] ?? null;
    }

    public function getSslStatusAttribute()
    {
        return $this->data['ssl_status'] ?? null;
    }

    public function getDnsRecordsAttribute()
    {
        return $this->data['dns_records'] ?? [];
    }

    public function hasSsl(): bool
    {
        return ($this->ssl_status['valid'] ?? false) === true;
    }

    public function getSslExpiryAttribute()
    {
        return $this->ssl_status['expires'] ?? null;
    }

    public function getSslIssuerAttribute()
    {
        return $this->ssl_status['issuer'] ?? 'Unknown';
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    public function getControlPanelNameAttribute()
    {
        return match ($this->control_panel)
        {
            'none' => 'Ninguno',
            'cpanel' => 'cPanel',
            'plesk' => 'Plesk',
            default => 'Desconocido'
        };
    }

    public function hasControlPanel(): bool
    {
        return $this->control_panel !== 'none';
    }

    public function usesCpanelAccountAuth(): bool
    {
        return $this->control_panel === 'cpanel'
            && ($this->data['auth_mode'] ?? 'whm') === 'cpanel_user';
    }

    public function getAuthModeLabelAttribute(): string
    {
        return $this->usesCpanelAccountAuth() ? 'cPanel account' : 'WHM API';
    }

    /**
     * @return array<int, string>
     */
    public function getProvisioningNameservers(): array
    {
        $configured = $this->data['provisioning_nameservers'] ?? null;

        if (is_array($configured) && $configured !== [])
        {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        if (is_string($configured) && $configured !== '')
        {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $configured) ?: [])));
        }

        return config('humano_hosting.default_nameservers', []);
    }

    public function getProvisioningSpfRecord(): string
    {
        $configured = $this->data['provisioning_spf'] ?? null;

        if (is_string($configured) && $configured !== '')
        {
            return $configured;
        }

        return (string) config('humano_hosting.default_spf_record', '');
    }

    // Check if server has a token configured
    public function hasToken(): bool
    {
        return ! empty($this->getRawEncryptedToken());
    }

    // Get decrypted token for API calls (use with caution)
    public function getDecryptedToken()
    {
        return $this->encrypted_token; // This will use the getter to decrypt
    }

    // Get WHM authorization header if applicable
    public function getWhmAuthHeader()
    {
        if ($this->control_panel === 'cpanel' && $this->hasToken())
        {
            return 'whm '.$this->username.':'.$this->getDecryptedToken();
        }

        return null;
    }

    public function getWebmailUrl(?string $email = null): ?string
    {
        if ($this->hostname === null || $this->hostname === '')
        {
            return null;
        }

        $url = 'https://'.$this->hostname.':2096/';

        if ($email !== null && $email !== '')
        {
            return $url.'login/?user='.urlencode($email);
        }

        return $url;
    }

    public function getCpanelUrl(): ?string
    {
        if ($this->hostname === null || $this->hostname === '')
        {
            return null;
        }

        return 'https://'.$this->hostname.':2083/';
    }
}
