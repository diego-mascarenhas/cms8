<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'domain',
        'server_url',
        'username',
        'plan',
        'status',
        'site_type',
        'php_version',
        'notes',
        'needs_update',
        'is_working',
        'data'
    ];

    protected $casts = [
        'status' => 'boolean',
        'needs_update' => 'boolean',
        'is_working' => 'boolean',
        'data' => 'array'
    ];

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
}
