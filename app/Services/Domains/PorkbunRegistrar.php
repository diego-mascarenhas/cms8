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
        $quote = is_array($payload['response'] ?? null) ? $payload['response'] : $payload;

        return [
            'domain' => $domain,
            'available' => strtolower((string) ($quote['avail'] ?? '')) === 'yes',
            'price' => isset($quote['price']) && is_numeric($quote['price']) ? round((float) $quote['price'], 2) : null,
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

        if (! is_array($payload))
        {
            throw new RuntimeException('Porkbun no respondió.');
        }

        if (strtoupper((string) ($payload['status'] ?? 'SUCCESS')) === 'ERROR' || $response->failed())
        {
            throw new RuntimeException($this->spanishError(
                strtoupper(trim((string) ($payload['code'] ?? ''))),
                trim((string) ($payload['message'] ?? '')),
            ));
        }

        return $payload;
    }

    private function spanishError(string $code, string $message): string
    {
        $domain = '';

        if (preg_match('/`([^`]+)`/', $message, $matches) === 1)
        {
            $domain = $matches[1];
        }

        $restricted = $domain !== ''
            ? 'Esta clave de API solo puede usarse en ciertos dominios y '.$domain.' no está en la lista permitida.'
            : 'Esta clave de API solo puede usarse en ciertos dominios y este no está en la lista permitida.';

        $known = [
            'DOMAIN_NOT_ALLOWED' => $restricted,
            'IP_NOT_ALLOWED' => 'Esta clave de API solo acepta peticiones desde ciertas direcciones IP.',
            'INVALID_API_KEYS_001' => 'La clave de API de Porkbun no es válida.',
            'INVALID_API_KEYS_002' => 'La clave secreta de Porkbun no coincide.',
            'MISSING_SECRETAPIKEY' => 'Falta la clave secreta de Porkbun.',
            'API_KEY_REQUIRED' => 'Falta la clave de API de Porkbun.',
            'API_ACCESS_DISABLED' => 'Este dominio no tiene el acceso por API activado en Porkbun.',
            'INSUFFICIENT_FUNDS' => 'No hay saldo suficiente en Porkbun para registrar el dominio.',
            'COST_MISMATCH' => 'El precio del dominio cambió. Consultalo de nuevo antes de registrarlo.',
            'INVALID_DOMAIN' => 'El dominio no es válido.',
            'RATE_LIMIT_EXCEEDED' => 'Porkbun limitó las consultas. Esperá un momento y volvé a intentar.',
            'INSUFFICIENT_SCOPE' => 'Esta clave de API no tiene permiso para esta operación.',
            'TERMS_NOT_AGREED' => 'Hay que aceptar los términos de Porkbun para registrar el dominio.',
            'ORDER_FAILED' => 'Porkbun no pudo crear el pedido. No se cobró nada.',
            'VERIFICATION_REQUIRED' => 'Hay que verificar el correo y el teléfono de la cuenta de Porkbun antes de registrar.',
            'ACCOUNT_VERIFICATION_REQUIRED' => 'Porkbun pide verificar la identidad de la cuenta antes de continuar.',
            'ID_VERIFICATION_REQUIRED' => 'Porkbun pide verificar la identidad antes de registrar este dominio.',
            'NEW_ACCOUNT_LIMIT' => 'La cuenta de Porkbun llegó al límite de registros de los primeros días.',
            'MONTHLY_SPEND_LIMIT_EXCEEDED' => 'Este registro superaría el límite mensual de gasto en Porkbun.',
            'DOMAIN_NOT_FOUND' => 'Ese dominio no está en la cuenta de Porkbun.',
        ];

        if (isset($known[$code]))
        {
            return $known[$code];
        }

        if (preg_match('/restricted to specific domains and `[^`]+` is not in the allowed list/i', $message) === 1)
        {
            return $restricted;
        }

        return 'Porkbun no pudo completar la operación.';
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
