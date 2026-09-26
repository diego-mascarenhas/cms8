<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RevisionAlphaBilling
{
    /**
     * @return array{invoices: array<int, mixed>, services: array<int, mixed>, payments: array<int, mixed>}
     */
    public function forEmail(string $email): array
    {
        $empty = [
            'invoices' => [],
            'services' => [],
            'payments' => [],
        ];

        $secret = (string) config('services.revisionalpha.portal_secret');
        $base = rtrim((string) config('services.revisionalpha.url'), '/');
        if ($secret === '' || $base === '')
        {
            return $empty;
        }

        try
        {
            $response = Http::withHeaders([
                'X-Portal-Secret' => $secret,
                'Accept' => 'application/json',
            ])->timeout(20)->post($base.'/api/portal/billing', [
                'email' => $email,
            ]);
        } catch (\Throwable $exception)
        {
            report($exception);

            return $empty;
        }

        if (! $response->successful())
        {
            return $empty;
        }

        $payload = $response->json();

        return [
            'invoices' => is_array($payload['invoices'] ?? null) ? $payload['invoices'] : [],
            'services' => is_array($payload['services'] ?? null) ? $payload['services'] : [],
            'payments' => is_array($payload['payments'] ?? null) ? $payload['payments'] : [],
        ];
    }
}
