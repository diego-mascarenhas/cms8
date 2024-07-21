<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class VCenterService
{
    protected $client;
    protected $host;
    protected $username;
    protected $password;
    protected $token;

    public function __construct()
    {
        $this->client = new Client([
            'verify' => false,
        ]);

        $this->host = env('VCENTER_HOST');
        $this->username = env('VCENTER_USERNAME');
        $this->password = env('VCENTER_PASSWORD');
    }

    public function authenticate()
    {
        try
        {
            $response = $this->client->post($this->host . '/rest/com/vmware/cis/session', [
                'auth' => [$this->username, $this->password]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            if (!isset($body['value']))
            {
                throw new \Exception('Did not receive VMware session ID.');
            }

            $this->token = $body['value'];
        }
        catch (RequestException $e)
        {
            throw new \Exception('Authentication error: ' . $e->getMessage());
        }
    }

    public function getHosts()
    {
        if (!$this->token)
        {
            $this->authenticate();
        }

        try
        {
            $response = $this->client->get($this->host . '/rest/vcenter/host', [
                'headers' => [
                    'vmware-api-session-id' => $this->token
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        }
        catch (RequestException $e)
        {
            throw new \Exception('Error retrieving hosts: ' . $e->getMessage());
        }
    }

    public function getVMs()
    {
        if (!$this->token)
        {
            $this->authenticate();
        }

        try
        {
            $response = $this->client->get($this->host . '/rest/vcenter/vm', [
                'headers' => [
                    'vmware-api-session-id' => $this->token
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        }
        catch (RequestException $e)
        {
            throw new \Exception('Error retrieving VMs: ' . $e->getMessage());
        }
    }
    
}
