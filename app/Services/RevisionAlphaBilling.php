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
        $payload = $this->post('/api/portal/billing', $email);

        return [
            'invoices' => is_array($payload['invoices'] ?? null) ? $payload['invoices'] : [],
            'services' => is_array($payload['services'] ?? null) ? $payload['services'] : [],
            'payments' => is_array($payload['payments'] ?? null) ? $payload['payments'] : [],
        ];
    }

    /**
     * @return array{name: string, phone: string, company_name: string, tax_id: string, payment_method: array<string, mixed>|null}
     */
    public function profile(string $email): array
    {
        $payload = $this->post('/api/portal/profile', $email);

        return [
            'name' => (string) ($payload['name'] ?? ''),
            'phone' => (string) ($payload['phone'] ?? ''),
            'company_name' => (string) ($payload['company_name'] ?? ''),
            'tax_id' => (string) ($payload['tax_id'] ?? ''),
            'payment_method' => is_array($payload['payment_method'] ?? null) ? $payload['payment_method'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function post(string $path, string $email): array
    {
        $secret = (string) config('services.revisionalpha.portal_secret');
        $base = rtrim((string) config('services.revisionalpha.url'), '/');
        if ($secret === '' || $base === '')
        {
            return [];
        }

        try
        {
            $response = Http::withHeaders([
                'X-Portal-Secret' => $secret,
                'Accept' => 'application/json',
            ])->timeout(20)->post($base.$path, [
                'email' => $email,
            ]);
        } catch (\Throwable $exception)
        {
            report($exception);

            return [];
        }

        if (! $response->successful())
        {
            return [];
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }
}
