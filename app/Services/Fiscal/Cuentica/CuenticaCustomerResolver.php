<?php

namespace App\Services\Fiscal\Cuentica;

use App\Models\Enterprise;
use App\Models\FiscalCustomerMapping;
use App\Services\Fiscal\Exceptions\FiscalExportException;

class CuenticaCustomerResolver
{
    public const PLATFORM = 'cuentica';

    /**
     * Resolve (or create) the Cuéntica customer id for an enterprise.
     */
    public function resolve(CuenticaApiClient $client, Enterprise $enterprise): int
    {
        $mapping = FiscalCustomerMapping::query()
            ->where('enterprise_id', $enterprise->id)
            ->where('platform', self::PLATFORM)
            ->first();

        if ($mapping instanceof FiscalCustomerMapping && filled($mapping->external_customer_id))
        {
            return (int) $mapping->external_customer_id;
        }

        $taxId = $this->taxId($enterprise);
        if ($taxId !== null)
        {
            $existing = $client->findCustomerByTaxId($taxId);
            if (is_array($existing) && isset($existing['id']))
            {
                return $this->storeMapping($enterprise, (int) $existing['id']);
            }
        }

        $created = $client->createCustomer($this->buildPayload($enterprise, $taxId));

        if (! isset($created['id']))
        {
            throw FiscalExportException::transient('Cuéntica did not return a customer id.');
        }

        return $this->storeMapping($enterprise, (int) $created['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Enterprise $enterprise, ?string $taxId): array
    {
        $billing = $enterprise->enterpriseBillingAddress();

        $address = trim((string) ($billing->address ?? $enterprise->address ?? ''));
        $town = trim((string) ($billing->locality ?? $enterprise->locality ?? ''));
        $postalCode = trim((string) ($billing->postal_code ?? $enterprise->postal_code ?? ''));
        $province = trim((string) ($billing->province ?? $enterprise->province ?? ''));
        $country = strtoupper(trim((string) ($billing->country ?? $enterprise->country ?? '')))
            ?: (string) config('fiscal.platforms.cuentica.default_country_code', 'ES');
        $name = trim((string) ($billing->name ?? $enterprise->name ?? ''));

        $missing = [];
        if ($address === '')
        {
            $missing[] = 'address';
        }
        if ($town === '')
        {
            $missing[] = 'town/locality';
        }
        if ($postalCode === '')
        {
            $missing[] = 'postal_code';
        }
        if ($name === '')
        {
            $missing[] = 'name';
        }
        if ($country === 'ES' && $province === '')
        {
            $missing[] = 'province/region';
        }

        if ($missing !== [])
        {
            throw FiscalExportException::validation(
                'Enterprise #'.$enterprise->id.' is missing fiscal data for Cuéntica: '.implode(', ', $missing),
            );
        }

        $businessType = (string) config('fiscal.platforms.cuentica.default_business_type', 'company');

        $payload = [
            'address' => $address,
            'town' => $town,
            'postal_code' => $postalCode,
            'tradename' => $name,
            'business_type' => $businessType,
            'country_code' => $country,
        ];

        if ($businessType === 'individual')
        {
            $payload['name'] = $name;
        } else
        {
            $payload['business_name'] = $name;
        }

        if ($country === 'ES')
        {
            $payload['region'] = $province;
        }

        if ($taxId !== null)
        {
            $payload['tax_id'] = $taxId;
        }

        if (filled($enterprise->email))
        {
            $payload['email'] = (string) $enterprise->email;
        }

        if (filled($enterprise->phone))
        {
            $payload['phone'] = (string) $enterprise->phone;
        }

        return $payload;
    }

    private function taxId(Enterprise $enterprise): ?string
    {
        $billing = $enterprise->enterpriseBillingAddress();
        $taxId = trim((string) ($billing->identification_number ?? ''));

        if ($taxId === '')
        {
            $taxId = trim((string) data_get($enterprise->data, 'tax_id', ''));
        }

        return $taxId !== '' ? $taxId : null;
    }

    private function storeMapping(Enterprise $enterprise, int $externalId): int
    {
        FiscalCustomerMapping::query()->updateOrCreate(
            [
                'enterprise_id' => $enterprise->id,
                'platform' => self::PLATFORM,
            ],
            [
                'team_id' => $enterprise->team_id,
                'external_customer_id' => (string) $externalId,
                'synced_at' => now(),
            ],
        );

        return $externalId;
    }
}
