<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OvhApiController extends Controller
{
    private $apiEndpoint;

    private $appKey;

    private $appSecret;

    private $consumerKey;

    public function __construct()
    {
        $this->apiEndpoint = config('services.ovh.endpoint');
        $this->appKey = config('services.ovh.app_key');
        $this->appSecret = config('services.ovh.app_secret');
        $this->consumerKey = config('services.ovh.consumer_key');
    }

    /**
     * Generate OVH API signature
     */
    private function generateSignature($method, $url, $body, $timestamp)
    {
        $toSign = $this->appSecret.'+'.$this->consumerKey.'+'.$method.'+'.$url.'+'.$body.'+'.$timestamp;

        return '$1$'.sha1($toSign);
    }

    /**
     * Make an authenticated request to OVH API
     */
    private function makeRequest($method, $path, $body = '')
    {
        $url = $this->apiEndpoint.$path;
        $timestamp = time();

        $headers = [
            'X-Ovh-Application' => $this->appKey,
            'X-Ovh-Consumer' => $this->consumerKey,
            'X-Ovh-Timestamp' => $timestamp,
            'X-Ovh-Signature' => $this->generateSignature($method, $url, $body, $timestamp),
            'Content-Type' => 'application/json',
        ];

        $response = Http::withHeaders($headers)->send($method, $url, ['body' => $body]);

        return $response->json();
    }

    /**
     * Get all invoices
     */
    public function getInvoices()
    {
        try
        {
            // Get list of invoice IDs
            $invoiceIds = $this->makeRequest('GET', '/me/bill');

            $invoices = [];
            foreach ($invoiceIds as $id)
            {
                $invoice = $this->makeRequest('GET', "/me/bill/{$id}");
                $invoices[] = $invoice;
            }

            // Save to JSON file
            Storage::put('ovh_data/invoices.json', json_encode($invoices, JSON_PRETTY_PRINT));

            return response()->json([
                'status' => 'success',
                'message' => 'Invoices fetched successfully',
                'count' => count($invoices),
                'data' => $invoices,
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch invoices: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all services
     */
    public function getServices()
    {
        try
        {
            // Get list of service IDs
            $serviceIds = $this->makeRequest('GET', '/service');

            $services = [];
            foreach ($serviceIds as $id)
            {
                $service = $this->makeRequest('GET', "/service/{$id}");
                $services[] = $service;
            }

            // Save to JSON file
            Storage::put('ovh_data/services.json', json_encode($services, JSON_PRETTY_PRINT));

            return response()->json([
                'status' => 'success',
                'message' => 'Services fetched successfully',
                'count' => count($services),
                'data' => $services,
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch services: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all services raw (for use by the job)
     *
     * @return array
     */
    public function getServicesRaw()
    {
        try
        {
            // Get list of service IDs
            $serviceIds = $this->makeRequest('GET', '/service');

            $services = [];
            foreach ($serviceIds as $id)
            {
                $service = $this->makeRequest('GET', "/service/{$id}");

                // For web hosting services, fetch additional details
                if (isset($service['category']) && $service['category'] === 'hosting')
                {
                    $details = $this->makeRequest('GET', "/hosting/web/{$service['domain']}");
                    $service = array_merge($service, $details);
                }

                $services[] = $service;
            }

            return [
                'status' => 'success',
                'count' => count($services),
                'data' => $services,
            ];
        } catch (\Exception $e)
        {
            return [
                'status' => 'error',
                'message' => 'Failed to fetch services: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Dashboard view displaying both invoices and services
     */
    public function dashboard()
    {
        return view('ovh.dashboard');
    }

    /**
     * Run the domain sync job manually
     *
     * @return \Illuminate\Http\Response
     */
    public function syncDomains()
    {
        dispatch(new \App\Jobs\OvhServiceSync);

        return response()->json([
            'status' => 'success',
            'message' => 'OVH domain sync job dispatched successfully',
        ]);
    }
}
