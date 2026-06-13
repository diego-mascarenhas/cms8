<?php

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FiscalExportAdapter;
use App\Models\Invoice;
use App\Models\Team;

class FiscalExportRouter
{
    /**
     * @param  array<string, FiscalExportAdapter>  $adapters  keyed by platform name
     */
    public function __construct(
        private readonly array $adapters,
        private readonly NullFiscalExportAdapter $nullAdapter,
    ) {}

    public function resolve(Invoice $invoice): FiscalExportAdapter
    {
        $platform = $this->resolvePlatform($invoice);

        if ($platform === null || $platform === NullFiscalExportAdapter::PLATFORM)
        {
            return $this->nullAdapter;
        }

        $adapter = $this->adapters[$platform] ?? null;

        if ($adapter instanceof FiscalExportAdapter && $adapter->supports($invoice))
        {
            return $adapter;
        }

        return $this->nullAdapter;
    }

    public function resolvePlatform(Invoice $invoice): ?string
    {
        $team = $invoice->team;

        if ($team instanceof Team)
        {
            $explicit = trim((string) $team->getSetting('fiscal_platform', ''));
            if ($explicit !== '')
            {
                return strtolower($explicit);
            }

            $country = $this->teamCountry($team);
            if ($country !== null)
            {
                $byCountry = config('fiscal.default_platform_by_country.'.$country);
                if (is_string($byCountry) && $byCountry !== '')
                {
                    return $byCountry;
                }
            }
        }

        $default = config('fiscal.default_platform');

        return is_string($default) && $default !== '' ? $default : null;
    }

    private function teamCountry(Team $team): ?string
    {
        $country = strtoupper(trim((string) $team->getSetting('fiscal_country', '')));

        return $country !== '' ? $country : null;
    }
}
