<?php

namespace App\Services\Finance;

use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Services\TaxIdentifierService;

class EnterpriseVatRateResolver
{
    public function __construct(private readonly TaxIdentifierService $taxIdentifiers) {}

    /**
     * Resolve whether IVA applies and at which percent for a client enterprise.
     *
     * @return array{applies: bool, percent: float, label: string, reason: string}
     */
    public function resolve(?Enterprise $enterprise): array
    {
        $defaultPercent = (float) config('fiscal.platforms.cuentica.default_tax_percent', 21);

        $address = $enterprise?->enterpriseBillingAddress();
        if (! $address instanceof EnterpriseBillingAddress)
        {
            return [
                'applies' => true,
                'percent' => $defaultPercent,
                'label' => __('IVA :percent%', ['percent' => rtrim(rtrim(number_format($defaultPercent, 2, ',', ''), '0'), ',')]),
                'reason' => 'default_no_billing_address',
            ];
        }

        $statusName = mb_strtolower(trim((string) ($address->taxStatusType?->name ?? '')));
        if ($this->statusLooksExempt($statusName))
        {
            return [
                'applies' => false,
                'percent' => 0.0,
                'label' => __('Exempt from IVA'),
                'reason' => 'tax_status_exempt',
            ];
        }

        if ($this->statusLooksNonResident($statusName))
        {
            return [
                'applies' => false,
                'percent' => 0.0,
                'label' => __('Exempt from IVA'),
                'reason' => 'non_resident',
            ];
        }

        $country = strtoupper(trim((string) ($address->country ?? 'ES')));
        if ($country === '')
        {
            $country = 'ES';
        }

        $normalizedId = $this->taxIdentifiers->normalize((string) ($address->identification_number ?? ''));
        $stripeType = $normalizedId !== ''
            ? $this->taxIdentifiers->resolveStripeTaxIdType($country, $normalizedId)
            : null;

        // Other EU VAT IDs: reverse charge (0%). Spanish domestic IDs keep IVA.
        if ($stripeType === 'eu_vat' && $country !== 'ES' && ! str_starts_with($normalizedId, 'ES'))
        {
            return [
                'applies' => false,
                'percent' => 0.0,
                'label' => __('EU reverse charge (0% IVA)'),
                'reason' => 'eu_reverse_charge',
            ];
        }

        if ($country !== 'ES' && $stripeType !== 'eu_vat' && $stripeType !== 'es_cif')
        {
            return [
                'applies' => false,
                'percent' => 0.0,
                'label' => __('Exempt from IVA'),
                'reason' => 'export_outside_es',
            ];
        }

        return [
            'applies' => true,
            'percent' => $defaultPercent,
            'label' => __('IVA :percent%', ['percent' => rtrim(rtrim(number_format($defaultPercent, 2, ',', ''), '0'), ',')]),
            'reason' => 'domestic_or_es_vat',
        ];
    }

    private function statusLooksExempt(string $statusName): bool
    {
        if ($statusName === '')
        {
            return false;
        }

        return str_contains($statusName, 'exempt')
            || str_contains($statusName, 'exent')
            || str_contains($statusName, 'exento');
    }

    private function statusLooksNonResident(string $statusName): bool
    {
        if ($statusName === '')
        {
            return false;
        }

        return str_contains($statusName, 'non-resident')
            || str_contains($statusName, 'no residente')
            || str_contains($statusName, 'non resident');
    }
}
