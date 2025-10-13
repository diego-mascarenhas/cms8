<?php

namespace App\Services;

class DomainInfoService
{
    public function getDomainInfo(string $domain)
    {
        return [
            'web_ip' => $this->getWebIP($domain),
            'mail_ip' => $this->getMailIP($domain),
            'ssl_status' => $this->checkSSL($domain),
            'dns_records' => $this->getDNSRecords($domain),
        ];
    }

    private function getWebIP(string $domain)
    {
        try
        {
            $ips = dns_get_record($domain, DNS_A);

            return $ips[0]['ip'] ?? null;
        } catch (\Exception $e)
        {
            return null;
        }
    }

    private function getMailIP(string $domain)
    {
        try
        {
            $mxRecords = dns_get_record($domain, DNS_MX);
            if (! empty($mxRecords))
            {
                $mailServer = $mxRecords[0]['target'];
                $mailIps = dns_get_record($mailServer, DNS_A);

                return $mailIps[0]['ip'] ?? null;
            }

            return null;
        } catch (\Exception $e)
        {
            return null;
        }
    }

    private function getDNSRecords(string $domain)
    {
        try
        {
            return [
                'a' => dns_get_record($domain, DNS_A),
                'mx' => dns_get_record($domain, DNS_MX),
                'txt' => dns_get_record($domain, DNS_TXT),
                'ns' => dns_get_record($domain, DNS_NS),
                'cname' => dns_get_record($domain, DNS_CNAME),
            ];
        } catch (\Exception $e)
        {
            return [];
        }
    }

    private function checkSSL(string $domain)
    {
        try
        {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'capture_peer_cert' => true,
                ],
            ]);

            $client = stream_socket_client(
                "ssl://{$domain}:443",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context,
            );

            if ($client)
            {
                $params = stream_context_get_params($client);
                $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

                return [
                    'valid' => true,
                    'expires' => date('Y-m-d H:i:s', $cert['validTo_time_t']),
                    'issuer' => $cert['issuer']['O'] ?? 'Unknown',
                ];
            }

            return ['valid' => false];
        } catch (\Exception $e)
        {
            return ['valid' => false];
        }
    }
}
