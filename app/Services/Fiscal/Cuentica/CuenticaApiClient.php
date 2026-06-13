<?php

namespace App\Services\Fiscal\Cuentica;

use App\Services\Fiscal\Exceptions\FiscalExportException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class CuenticaApiClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = 'https://api.cuentica.com',
        private readonly int $timeout = 30,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getCompany(): array
    {
        return $this->request('get', '/company')->json();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listCustomers(array $filters = []): array
    {
        return $this->request('get', '/customer', $filters)->json() ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCustomerByTaxId(string $taxId): ?array
    {
        $taxId = trim($taxId);
        if ($taxId === '')
        {
            return null;
        }

        $customers = $this->listCustomers(['tax_id' => $taxId, 'page_size' => 50]);

        foreach ($customers as $customer)
        {
            if (strcasecmp(trim((string) ($customer['tax_id'] ?? '')), $taxId) === 0)
            {
                return $customer;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCustomer(array $data): array
    {
        return $this->request('post', '/customer', $data)->json();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createInvoice(array $data): array
    {
        return $this->request('post', '/invoice', $data)->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getInvoice(int|string $id): array
    {
        return $this->request('get', '/invoice/'.$id)->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $uri, array $payload = []): Response
    {
        $request = $this->pendingRequest();

        $response = $method === 'get'
            ? $request->get($uri, $payload)
            : $request->{$method}($uri, $payload);

        return $this->handle($response);
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-AUTH-TOKEN' => $this->token]);
    }

    private function handle(Response $response): Response
    {
        if ($response->successful())
        {
            return $response;
        }

        $status = $response->status();
        $message = $this->extractMessage($response);

        if ($status === 429)
        {
            throw FiscalExportException::rateLimited(
                'Cuéntica rate limit reached: '.$message,
                $this->retryAfterSeconds($response),
            );
        }

        if ($status === 403 || $status === 401)
        {
            throw FiscalExportException::validation('Cuéntica authentication failed (token): '.$message);
        }

        if ($status >= 500)
        {
            throw FiscalExportException::transient('Cuéntica server error ('.$status.'): '.$message);
        }

        throw FiscalExportException::validation('Cuéntica request rejected ('.$status.'): '.$message);
    }

    private function extractMessage(Response $response): string
    {
        $body = $response->json();

        if (is_array($body))
        {
            $message = $body['message'] ?? $body['error'] ?? null;
            if (is_string($message) && $message !== '')
            {
                return $message;
            }

            return json_encode($body) ?: $response->body();
        }

        return $response->body();
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $reset = $response->header('X-RateLimit-Reset');
        if ($reset !== '' && is_numeric($reset))
        {
            return max(1, (int) $reset - time());
        }

        $retryAfter = $response->header('Retry-After');
        if ($retryAfter !== '' && is_numeric($retryAfter))
        {
            return max(1, (int) $retryAfter);
        }

        return null;
    }
}
