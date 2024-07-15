<?php

if (!function_exists('getWHMServers')) {
    function getWHMServers()
    {
        $servers = env('WHM_SERVERS');
        $serversArray = explode(',', $servers);
        $formattedServers = [];

        foreach ($serversArray as $server) {
            list($host, $username, $token) = explode(':', $server);
            $formattedServers[] = [
                'host' => $host,
                'username' => $username,
                'token' => $token,
            ];
        }

        return $formattedServers;
    }
}
