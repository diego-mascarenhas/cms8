<?php

namespace App\Services\Fiscal\Cuentica;

use App\Enums\CuenticaInboundDocumentKind;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\InvoiceSync;
use Illuminate\Support\Arr;

class CuenticaCounterpartyResolver
{
    public function findOrCreateFromSyncRow(
        InvoiceSync $row,
        CuenticaInboundDocumentKind $kind,
        bool $dryRun = false,
    ): ?Enterprise {
        $payload = is_array($row->raw_payload) ? $row->raw_payload : [];
        $entity = $this->resolveCounterpartyEntity($payload, $kind, $row);

        if ($entity === [])
        {
            return null;
        }

        $codePrefix = $kind === CuenticaInboundDocumentKind::Sale ? 'cuentica_c_' : 'cuentica_p_';
        $cuenticaId = trim((string) ($row->customer_id ?? Arr::get($entity, 'id') ?? ''));
        $code = $cuenticaId !== '' ? $codePrefix.$cuenticaId : null;

        if ($code)
        {
            $existing = Enterprise::withoutGlobalScopes()
                ->where('team_id', $row->team_id)
                ->where('type_id', $kind->enterpriseTypeId())
                ->where('code', $code)
                ->first();

            if ($existing)
            {
                return $existing;
            }
        }

        $taxId = strtoupper(trim((string) (
            $row->customer_tax_id
            ?? Arr::get($entity, 'tax_id')
            ?? Arr::get($entity, 'cif')
            ?? ''
        )));

        if ($taxId !== '')
        {
            $billingMatch = EnterpriseBillingAddress::query()
                ->whereHas('enterprise', function ($query) use ($row, $kind)
                {
                    $query->where('team_id', $row->team_id)
                        ->where('type_id', $kind->enterpriseTypeId());
                })
                ->whereRaw('UPPER(identification_number) = ?', [$taxId])
                ->first();

            if ($billingMatch?->enterprise)
            {
                return $billingMatch->enterprise;
            }
        }

        if ($dryRun)
        {
            return null;
        }

        $name = trim((string) (
            $row->customer_name
            ?? Arr::get($entity, 'business_name')
            ?? Arr::get($entity, 'tradename')
            ?? 'Cuéntica '.$cuenticaId
        ));

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $row->team_id,
            'type_id' => $kind->enterpriseTypeId(),
            'status_id' => 1,
            'name' => $name,
            'code' => $code,
            'email' => trim((string) (Arr::get($entity, 'email') ?: $row->customer_email ?: '')) ?: null,
            'address' => Arr::get($entity, 'address'),
            'postal_code' => Arr::get($entity, 'postal_code'),
            'locality' => Arr::get($entity, 'town'),
            'province' => Arr::get($entity, 'region'),
            'country' => strtoupper((string) (Arr::get($entity, 'country_code') ?: 'ES')),
            'phone' => Arr::get($entity, 'phone'),
        ]);

        if ($taxId !== '')
        {
            EnterpriseBillingAddress::query()->create([
                'enterprise_id' => $enterprise->id,
                'name' => $name,
                'tax_status_type_id' => 1,
                'identification_number' => $taxId,
                'address' => Arr::get($entity, 'address'),
                'postal_code' => Arr::get($entity, 'postal_code'),
                'locality' => Arr::get($entity, 'town'),
                'province' => Arr::get($entity, 'region'),
                'country' => strtoupper((string) (Arr::get($entity, 'country_code') ?: 'ES')),
                'status' => 1,
            ]);
        }

        return $enterprise;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveCounterpartyEntity(array $payload, CuenticaInboundDocumentKind $kind, InvoiceSync $row): array
    {
        $value = Arr::get($payload, $kind->counterpartyKey());

        if (is_array($value) && $value !== [])
        {
            return $value;
        }

        if ($value !== null && $value !== '')
        {
            return ['id' => $value];
        }

        if (filled($row->customer_id))
        {
            return ['id' => $row->customer_id];
        }

        if (filled($row->customer_name) || filled($row->customer_tax_id))
        {
            return [
                'id' => $row->customer_id,
                'business_name' => $row->customer_name,
                'tax_id' => $row->customer_tax_id,
                'email' => $row->customer_email,
                'country_code' => $row->customer_address_country,
            ];
        }

        return [];
    }
}
