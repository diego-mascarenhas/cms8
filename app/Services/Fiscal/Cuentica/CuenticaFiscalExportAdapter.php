<?php

namespace App\Services\Fiscal\Cuentica;

use App\Contracts\Fiscal\FiscalExportAdapter;
use App\Models\Enterprise;
use App\Models\FiscalExport;
use App\Models\Invoice;
use App\Models\Team;
use App\Services\Fiscal\Exceptions\FiscalExportException;
use App\Services\Fiscal\FiscalExportResult;

class CuenticaFiscalExportAdapter implements FiscalExportAdapter
{
    public const PLATFORM = 'cuentica';

    public function __construct(
        private readonly CuenticaClientFactory $clientFactory,
        private readonly CuenticaCustomerResolver $customerResolver,
        private readonly CuenticaInvoiceMapper $invoiceMapper,
    ) {}

    public function platform(): string
    {
        return self::PLATFORM;
    }

    public function supports(Invoice $invoice): bool
    {
        if (! (bool) config('fiscal.platforms.cuentica.enabled', false))
        {
            return false;
        }

        $team = $invoice->team;

        return $team instanceof Team && $this->clientFactory->tokenForTeam($team) !== null;
    }

    public function export(Invoice $invoice): FiscalExportResult
    {
        [$client, $enterprise] = $this->prepare($invoice);

        $customerId = $this->customerResolver->resolve($client, $enterprise);
        $payload = $this->invoiceMapper->map($invoice, $customerId);

        $response = $client->createInvoice($payload);

        if (! isset($response['id']))
        {
            throw FiscalExportException::transient('Cuéntica did not return an invoice id.');
        }

        return FiscalExportResult::exported(
            externalId: (string) $response['id'],
            externalNumber: isset($response['number']) ? (string) $response['number'] : null,
            externalCustomerId: (string) $customerId,
            payload: $payload,
            response: $response,
        );
    }

    public function voidOrRectify(Invoice $invoice, FiscalExport $existing): FiscalExportResult
    {
        if (blank($existing->external_id))
        {
            throw FiscalExportException::validation('Cannot rectify: missing Cuéntica invoice id.');
        }

        [$client, $enterprise] = $this->prepare($invoice);

        $customerId = $existing->external_customer_id
            ? (int) $existing->external_customer_id
            : $this->customerResolver->resolve($client, $enterprise);

        $payload = $this->invoiceMapper->map($invoice, $customerId);
        $payload['rectified_id'] = (int) $existing->external_id;
        $payload['rectification_key'] = 'R1';

        $response = $client->createInvoice($payload);

        if (! isset($response['id']))
        {
            throw FiscalExportException::transient('Cuéntica did not return a rectification id.');
        }

        return FiscalExportResult::rectified(
            externalId: (string) $response['id'],
            externalNumber: isset($response['number']) ? (string) $response['number'] : null,
            payload: $payload,
            response: $response,
        );
    }

    /**
     * @return array{0: CuenticaApiClient, 1: Enterprise}
     */
    private function prepare(Invoice $invoice): array
    {
        $team = $invoice->team;
        if (! $team instanceof Team)
        {
            throw FiscalExportException::validation('Invoice #'.$invoice->id.' has no team.');
        }

        $client = $this->clientFactory->forTeam($team);
        if ($client === null)
        {
            throw FiscalExportException::validation('No Cuéntica token configured for team #'.$team->id.'.');
        }

        $enterprise = $invoice->enterprise instanceof Enterprise
            ? $invoice->enterprise
            : Enterprise::withoutGlobalScopes()->find($invoice->enterprise_id);

        if (! $enterprise instanceof Enterprise)
        {
            throw FiscalExportException::validation('Invoice #'.$invoice->id.' has no enterprise.');
        }

        return [$client, $enterprise];
    }
}
