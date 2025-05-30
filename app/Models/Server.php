<?php

namespace App\Models;

use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $table = 'servers';

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
        'data'
    ];

    protected $casts = [
        'success' => 'boolean',
        'status_id' => ServerStatus::class,
        'data' => 'array'
    ];

    // Encrypt token when saving
    public function setEncryptedTokenAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['encrypted_token'] = encrypt($value);
        } else {
            $this->attributes['encrypted_token'] = null;
        }
    }

    // Decrypt token when accessing
    public function getEncryptedTokenAttribute($value)
    {
        if (!empty($value)) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
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
        return match($this->control_panel) {
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

    // Check if server has a token configured
    public function hasToken(): bool
    {
        return !empty($this->getRawEncryptedToken());
    }

    // Get decrypted token for API calls (use with caution)
    public function getDecryptedToken()
    {
        return $this->encrypted_token; // This will use the getter to decrypt
    }

    // Get WHM authorization header if applicable
    public function getWhmAuthHeader()
    {
        if ($this->control_panel === 'cpanel' && $this->hasToken()) {
            return 'whm ' . $this->username . ':' . $this->getDecryptedToken();
        }
        return null;
    }
} 