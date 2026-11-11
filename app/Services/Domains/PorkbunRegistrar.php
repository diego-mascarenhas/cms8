<?php

namespace App\Services\Domains;

use App\Models\Team;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PorkbunRegistrar
{
    /**
     * @return array{domain: string, available: bool, price: float|null, currency: string}
     */
    public function check(Team $team, string $domain): array
    {
        $domain = $this->domain($domain);
        $payload = $this->post($team, 'domain/checkDomain/'.$domain);

        return [
            'domain' => $domain,
            'available' => strtolower((string) ($payload['avail'] ?? '')) === 'yes',
            'price' => isset($payload['price']) && is_numeric($payload['price']) ? round((float) $payload['price'], 2) : null,
            'currency' => 'USD',
        ];
    }

    /**
     * @return array{domain: string, price: float|null, currency: string, nameservers: list<string>, nameservers_set: bool, message: string}
     */
    public function register(Team $team, string $domain): array
    {
        $quote = $this->check($team, $domain);

        if (! $quote['available'] || $quote['price'] === null)
        {
            throw new RuntimeException('Ese dominio no está disponible.');
        }

        $this->post($team, 'domain/create/'.$quote['domain'], [
            'cost' => (int) round($quote['price'] * 100),
            'agreeToTerms' => 'yes',
        ], 'register-'.$quote['domain']);

        $nameservers = $this->nameservers();
        $nameserversSet = true;
        $message = 'El dominio quedó registrado.';

        try
        {
            $this->post($team, 'domain/updateNs/'.$quote['domain'], [
                'ns' => $nameservers,
            ]);
        } catch (RuntimeException $exception)
        {
            $nameserversSet = false;
            $message = 'El dominio quedó registrado, pero no pude cambiar los DNS: '.$exception->getMessage();
        }

        return [
            'domain' => $quote['domain'],
            'price' => $quote['price'],
            'currency' => 'USD',
            'nameservers' => $nameservers,
            'nameservers_set' => $nameserversSet,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(Team $team, string $path, array $body = [], ?string $idempotency = null): array
    {
        [$key, $secret] = $this->credentials($team);

        if ($key === '' || $secret === '')
        {
            throw new RuntimeException('Porkbun no está configurado. Faltan la API key y la secret API key.');
        }

        $headers = [
            'X-API-Key' => $key,
            'X-Secret-API-Key' => $secret,
        ];

        if ($idempotency !== null)
        {
            $headers['Idempotency-Key'] = $idempotency;
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->withHeaders($headers)
            ->post('https://api.porkbun.com/api/json/v3/'.$path, $body);

        return $this->payload($response);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(Team $team): array
    {
        $key = trim((string) $team->getSetting('porkbun_api_key', ''));
        $secret = trim((string) $team->getSetting('porkbun_secret_api_key', ''));

        if ($key === '')
        {
            $key = trim((string) config('services.porkbun.key'));
        }

        if ($secret === '')
        {
            $secret = trim((string) config('services.porkbun.secret'));
        }

        return [$key, $secret];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload) || strtoupper((string) ($payload['status'] ?? 'SUCCESS')) === 'ERROR' || $response->failed())
        {
            $message = is_array($payload) ? trim((string) ($payload['message'] ?? '')) : '';

            throw new RuntimeException($message !== '' ? $message : 'Porkbun no respondió.');
        }

        return $payload;
    }

    private function domain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = explode('/', $value)[0];
        $value = rtrim($value, '.');

        if (str_starts_with($value, 'www.'))
        {
            $value = substr($value, 4);
        }

        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $value) !== 1)
        {
            throw new RuntimeException('El dominio no es válido.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function nameservers(): array
    {
        $names = config('humano_hosting.default_nameservers', []);

        return array_values(array_map(
            fn (mixed $name): string => strtolower(rtrim((string) $name, '.')),
            is_array($names) ? $names : [],
        ));
    }
}
