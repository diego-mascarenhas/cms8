<?php

namespace App\Models;

use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;

class Domain extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'domain',
        'server_id',
        'service_id',
        'username',
        'plan',
        'suspended',
        'site_type',
        'php_version',
        'notes',
        'needs_update',
        'is_working',
        'data',
    ];

    protected $casts = [
        'suspended' => 'boolean',
        'needs_update' => 'boolean',
        'is_working' => 'boolean',
        'data' => 'array',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCachedEmailAccounts(): array
    {
        return $this->data['email_accounts'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCachedMxRecords(): array
    {
        return $this->data['mx_records'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function getCachedAvailablePlans(): array
    {
        return $this->data['available_plans'] ?? [];
    }

    /**
     * @return array{used_mb: float, limit_mb: float|null, unlimited: bool, usage_percent: int}|null
     */
    public function getCachedAccountDisk(): ?array
    {
        $disk = $this->data['account_disk'] ?? null;

        return is_array($disk) ? $disk : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCachedPublicSpfCheck(): array
    {
        return $this->data['public_spf_check'] ?? [
            'exists' => false,
            'has_mailbaby' => false,
            'record' => null,
        ];
    }

    public function getCachedControlPanelError(): ?string
    {
        $error = $this->data['control_panel_error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    /**
     * @return array{web_ip: string|null, mail_ip: string|null, ssl_status: array<string, mixed>|null, nameservers: array<int, string>}
     */
    public function getCachedDisplayInfo(): array
    {
        $data = $this->data ?? [];

        return [
            'web_ip' => $this->web_ip ?? ($data['ip'] ?? null),
            'mail_ip' => $this->mail_ip,
            'ssl_status' => $this->ssl_status,
            'nameservers' => $data['nameservers'] ?? $this->server?->getProvisioningNameservers() ?? [],
        ];
    }

    public function isWordPress(): bool
    {
        try
        {
            $client = new Client(['timeout' => 5]);
            $response = $client->get('https://'.$this->domain.'/wp-json/wp/v2', [
                'http_errors' => false,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e)
        {
            return false;
        }
    }

    public function updateSiteType(): void
    {
        if ($this->isWordPress() && $this->site_type !== 'WordPress')
        {
            $this->site_type = 'WordPress';
            $this->save();
        }
    }

    public function getPhpVersionFromServer(): ?string
    {
        try
        {
            $server = $this->server;

            if (! $server || $server->control_panel !== 'cpanel' || ! $server->hasToken())
            {
                return null;
            }

            $url = "https://{$server->hostname}:2087/json-api/php_get_vhost_versions";
            $query = http_build_query([
                'api.filter.enable' => 1,
                'api.filter.a.field' => 'vhost',
                'api.filter.a.arg0' => $this->domain,
            ]);

            $response = Http::withHeaders([
                'Authorization' => $server->getWhmAuthHeader(),
            ])->get($url.'?'.$query);

            if (! $response->successful())
            {
                \Log::error("WHM API request failed for {$this->domain}: ".$response->body());

                return null;
            }

            $data = $response->json();

            // Check for the version in the response
            if (isset($data['data']['result'][0]['version']))
            {
                $version = $data['data']['result'][0]['version'];

                // Handle cases like "ea-php82"
                if (preg_match('/ea-php(\d+)/', $version, $matches))
                {
                    // Convert ea-php82 to 8.2
                    $majorMinor = $matches[1];

                    return substr($majorMinor, 0, 1).'.'.substr($majorMinor, 1);
                }

                return $version;
            }

            return null;
        } catch (\Exception $e)
        {
            // Log error
            \Log::error('Error fetching PHP version: '.$e->getMessage());

            return null;
        }
    }

    public function updatePhpVersion(): void
    {
        $phpVersion = $this->getPhpVersionFromServer();

        if ($phpVersion && $this->php_version !== $phpVersion)
        {
            $this->php_version = $phpVersion;
            $this->save();
        }
    }

    /**
     * Test method to simulate PHP version detection without actual API call
     * Use this for testing only
     */
    public function testUpdatePhpVersion(string $mockVersion = '8.1'): void
    {
        if ($this->php_version !== $mockVersion)
        {
            $this->php_version = $mockVersion;
            $this->save();
            \Log::info("Updated PHP version for {$this->domain} to {$mockVersion}");
        }
    }
}
