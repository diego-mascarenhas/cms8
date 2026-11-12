<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RevisionAlphaBilling
{
    /**
     * @return array{invoices: array<int, mixed>, services: array<int, mixed>, payments: array<int, mixed>}
     */
    public function forEmail(string $email): array
    {
        $key = 'revision-alpha-billing:'.sha1(mb_strtolower(trim($email)));
        $cached = Cache::get($key);
        if (is_array($cached))
        {
            return $cached;
        }

        $payload = $this->post('/api/portal/billing', $email);
        if ($payload === null)
        {
            return [
                'invoices' => [],
                'services' => [],
                'payments' => [],
            ];
        }

        $account = [
            'invoices' => is_array($payload['invoices'] ?? null) ? $payload['invoices'] : [],
            'services' => is_array($payload['services'] ?? null) ? $payload['services'] : [],
            'payments' => is_array($payload['payments'] ?? null) ? $payload['payments'] : [],
        ];
        Cache::put($key, $account, now()->addMinutes(3));

        return $account;
    }

    /**
     * @return array{name: string, phone: string, company_name: string, tax_id: string, payment_method: array<string, mixed>|null}
     */
    public function profile(string $email): array
    {
        $payload = $this->post('/api/portal/profile', $email) ?? [];

        return [
            'name' => (string) ($payload['name'] ?? ''),
            'phone' => (string) ($payload['phone'] ?? ''),
            'company_name' => (string) ($payload['company_name'] ?? ''),
            'tax_id' => (string) ($payload['tax_id'] ?? ''),
            'payment_method' => is_array($payload['payment_method'] ?? null) ? $payload['payment_method'] : null,
        ];
    }

    /**
     * Null when the portal could not be reached, so a failed call is not cached.
     *
     * @return array<string, mixed>|null
     */
    private function post(string $path, string $email): ?array
    {
        $secret = (string) config('services.revisionalpha.portal_secret');
        $base = rtrim((string) config('services.revisionalpha.url'), '/');
        if ($secret === '' || $base === '')
        {
            return null;
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

            return null;
        }

        if (! $response->successful())
        {
            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }
}
