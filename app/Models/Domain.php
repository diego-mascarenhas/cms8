<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use GuzzleHttp\Client;

class Domain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'domain',
        'server_url',
        'username',
        'plan',
        'suspended',
        'site_type',
        'php_version',
        'notes',
        'needs_update',
        'is_working',
        'data'
    ];

    protected $casts = [
        'suspended' => 'boolean',
        'needs_update' => 'boolean',
        'is_working' => 'boolean',
        'data' => 'array'
    ];

    public function server()
    {
        return $this->belongsTo(Server::class, 'server_url', 'server_url');
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

    public function isWordPress(): bool
    {
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get('https://' . $this->domain . '/wp-json/wp/v2', [
                'http_errors' => false
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateSiteType(): void
    {
        if ($this->isWordPress() && $this->site_type !== 'WordPress') {
            $this->site_type = 'WordPress';
            $this->save();
        }
    }
}
