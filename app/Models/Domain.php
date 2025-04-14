<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

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

    public function getPhpVersionFromServer(): ?string
    {
        try {
            // Get the server URL from relation
            $server = $this->server;
            
            if (!$server) {
                return null;
            }
            
            // Get the WHM servers configuration
            $serversString = env('WHM_SERVERS');
            if (empty($serversString)) {
                \Log::error("WHM_SERVERS environment variable not configured");
                return null;
            }
            
            // Find the matching server in the list
            $serversList = explode(',', $serversString);
            $serverConfig = null;
            
            foreach ($serversList as $serverString) {
                $serverParts = explode(':', trim($serverString));
                if (count($serverParts) >= 3 && $serverParts[0] === $server->server_url) {
                    $serverConfig = $serverParts;
                    break;
                }
            }
            
            if (!$serverConfig) {
                \Log::error("Server {$server->server_url} not found in WHM_SERVERS configuration");
                return null;
            }
            
            $hostname = $serverConfig[0];
            $username = $serverConfig[1];
            $token = $serverConfig[2];
            
            // Use the same approach as WhmService
            $url = "https://{$hostname}:2087/json-api/php_get_vhost_versions";
            $query = http_build_query([
                'api.filter.enable' => 1,
                'api.filter.a.field' => 'vhost',
                'api.filter.a.arg0' => $this->domain
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => 'whm ' . $username . ':' . $token,
            ])->get($url . '?' . $query);
            
            if (!$response->successful()) {
                \Log::error("WHM API request failed for {$this->domain}: " . $response->body());
                return null;
            }
            
            $data = $response->json();
            
            // Check for the version in the response
            if (isset($data['data']['result'][0]['version'])) {
                $version = $data['data']['result'][0]['version'];
                
                // Handle cases like "ea-php82"
                if (preg_match('/ea-php(\d+)/', $version, $matches)) {
                    // Convert ea-php82 to 8.2
                    $majorMinor = $matches[1];
                    return substr($majorMinor, 0, 1) . '.' . substr($majorMinor, 1);
                }
                
                return $version;
            }
            
            return null;
        } catch (\Exception $e) {
            // Log error
            \Log::error('Error fetching PHP version: ' . $e->getMessage());
            return null;
        }
    }
    
    public function updatePhpVersion(): void
    {
        $phpVersion = $this->getPhpVersionFromServer();
        
        if ($phpVersion && $this->php_version !== $phpVersion) {
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
        if ($this->php_version !== $mockVersion) {
            $this->php_version = $mockVersion;
            $this->save();
            \Log::info("Updated PHP version for {$this->domain} to {$mockVersion}");
        }
    }
}
