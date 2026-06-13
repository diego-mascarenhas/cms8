<?php

namespace App\Services\Fiscal\Cuentica;

use Illuminate\Support\Arr;

class CuenticaInboundPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeSale(array $payload): array
    {
        $customer = $this->entity($payload, 'customer');
        $amounts = $this->entity($payload, 'amount_details');

        $payload['total_base'] = $this->firstAmount(
            Arr::get($payload, 'total_base'),
            Arr::get($amounts, 'total_base'),
        );

        $payload['total_invoice'] = $this->firstAmount(
            Arr::get($payload, 'total_invoice'),
            Arr::get($amounts, 'total_invoice'),
            Arr::get($amounts, 'total_amount'),
        );

        $payload['customer_tax_id'] = $this->firstString(
            Arr::get($payload, 'customer_tax_id'),
            Arr::get($customer, 'tax_id'),
            Arr::get($customer, 'cif'),
        );

        $payload['customer_name'] = $this->firstString(
            Arr::get($payload, 'customer_name'),
            Arr::get($customer, 'business_name'),
            Arr::get($customer, 'tradename'),
        );

        $payload['customer_email'] = $this->firstString(
            Arr::get($payload, 'customer_email'),
            Arr::get($customer, 'email'),
        );

        $payload['customer_country'] = $this->firstString(
            Arr::get($payload, 'customer_country'),
            Arr::get($customer, 'country_code'),
        ) ?: 'ES';

        $payload['number'] = $this->firstString(
            Arr::get($payload, 'number'),
            $this->formatDocumentNumber(
                Arr::get($payload, 'invoice_serie'),
                Arr::get($payload, 'invoice_number'),
            ),
        );

        if (! isset($payload['customer']) && $customer !== [])
        {
            $payload['customer'] = $customer;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePurchase(array $payload): array
    {
        $provider = $this->entityReference($payload, 'provider');
        $amounts = $this->entity($payload, 'amount_details');

        $payload['total_base'] = $this->firstAmount(
            Arr::get($payload, 'total_base'),
            Arr::get($amounts, 'total_base'),
        );

        $payload['total_expense'] = $this->firstAmount(
            Arr::get($payload, 'total_expense'),
            Arr::get($amounts, 'total_expense'),
            Arr::get($amounts, 'total_amount'),
        );

        $payload['provider_tax_id'] = $this->firstString(
            Arr::get($payload, 'provider_tax_id'),
            Arr::get($provider, 'tax_id'),
            Arr::get($provider, 'cif'),
        );

        $payload['provider_name'] = $this->firstString(
            Arr::get($payload, 'provider_name'),
            Arr::get($provider, 'business_name'),
            Arr::get($provider, 'tradename'),
        );

        $payload['provider_email'] = $this->firstString(
            Arr::get($payload, 'provider_email'),
            Arr::get($provider, 'email'),
        );

        $payload['provider_country'] = $this->firstString(
            Arr::get($payload, 'provider_country'),
            Arr::get($provider, 'country_code'),
        ) ?: 'ES';

        $payload['document_number'] = $this->firstString(
            Arr::get($payload, 'document_number'),
            $this->formatDocumentNumber(
                Arr::get($payload, 'invoice_serie'),
                Arr::get($payload, 'document_number'),
            ),
        );

        if ($provider !== [])
        {
            $payload['provider'] = $provider;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function entity(array $payload, string $key): array
    {
        $value = Arr::get($payload, $key);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function entityReference(array $payload, string $key): array
    {
        $value = Arr::get($payload, $key);

        if (is_array($value))
        {
            return $value;
        }

        if ($value !== null && $value !== '')
        {
            return ['id' => $value];
        }

        return [];
    }

    private function formatDocumentNumber(mixed $serie, mixed $number): ?string
    {
        if ($number === null || $number === '')
        {
            return null;
        }

        $serie = trim((string) $serie);
        $number = trim((string) $number);

        if ($serie === '')
        {
            return $number;
        }

        return $serie.'-'.$number;
    }

    private function firstAmount(mixed ...$values): ?float
    {
        foreach ($values as $value)
        {
            if ($value === null || $value === '')
            {
                continue;
            }

            return max(0.0, round((float) $value, 2));
        }

        return null;
    }

    private function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value)
        {
            $string = trim((string) $value);
            if ($string !== '')
            {
                return $string;
            }
        }

        return null;
    }
}
