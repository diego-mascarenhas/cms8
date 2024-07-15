<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class WHMService
{
    protected $client;
    protected $whmServers;

    public function __construct()
    {
        $this->client = new Client(['verify' => false]);
        $this->whmServers = getWHMServers();
    }

    public function getServiceStatuses()
    {
        $statuses = [];

        foreach ($this->whmServers as $server)
        {
            try
            {
                //$response = $this->client->get("https://{$server['host']}:2087/json-api/loadavg?api.version=1", [
                $response = $this->client->get("https://{$server['host']}:2087/json-api/listaccts?api.version=1", [
                    'headers' => [
                        'Authorization' => 'whm ' . $server['username'] . ':' . $server['token'],
                        'Accept' => 'application/json',
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                if (isset($data['cpanelresult']['error']))
                {
                    throw new \Exception($data['cpanelresult']['error']);
                }
                $statuses[$server['host']] = $data['data'];
            }
            catch (RequestException $e)
            {
                Log::error("Error retrieving data from {$server['host']}: " . $e->getMessage());
                $statuses[$server['host']] = ['error' => 'Could not retrieve data'];
            }
            catch (\Exception $e)
            {
                Log::error("Access error on {$server['host']}: " . $e->getMessage());
                $statuses[$server['host']] = ['error' => 'Access denied'];
            }
        }

        return $statuses;
    }
}
