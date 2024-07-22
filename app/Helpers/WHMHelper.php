<?php

if (!function_exists('getWHMServers'))
{
    function getWHMServers()
    {
        $servers = env('WHM_SERVERS');

        if (!$servers)
        {
            return [];
        }

        $serversArray = explode(',', $servers);
        $formattedServers = [];

        foreach ($serversArray as $server)
        {
            $serverDetails = explode(':', $server);

            if (count($serverDetails) !== 3)
            {
                continue;
            }

            list($host, $username, $token) = $serverDetails;
            $formattedServers[] = [
                'host' => $host,
                'username' => $username,
                'token' => $token,
            ];
        }

        return $formattedServers;
    }
}
