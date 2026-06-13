<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Fiscal\Cuentica\CuenticaClientFactory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class SeedCuenticaSandboxDocumentsCommand extends Command
{
    protected $signature = 'cuentica:seed-sandbox-documents
                            {--team_id= : Team with cuentica_api_token (required)}
                            {--customer_id= : Existing Cuéntica customer id for sale invoice}
                            {--provider_id= : Existing Cuéntica provider id for purchase expense}
                            {--tag=humano-sync-test : Tag applied to both documents}';

    protected $description = 'Create one sale invoice and one purchase expense in Cuéntica sandbox for sync testing';

    public function handle(CuenticaClientFactory $clientFactory): int
    {
        $teamId = (int) ($this->option('team_id') ?? 0);
        if ($teamId <= 0)
        {
            $this->error('Pass --team_id= with a team that has cuentica_api_token configured.');

            return self::INVALID;
        }

        $team = Team::query()->find($teamId);
        if (! $team)
        {
            $this->error("Team {$teamId} not found.");

            return self::FAILURE;
        }

        $client = $clientFactory->forTeam($team);
        if ($client === null)
        {
            $this->error("Team {$teamId} has no cuentica_api_token.");

            return self::FAILURE;
        }

        $company = $client->getCompany();
        $sandbox = (bool) Arr::get($company, 'sandbox', false);
        $this->line('Company: '.(string) Arr::get($company, 'name', '?').' | sandbox='.($sandbox ? 'yes' : 'no'));

        if (! $sandbox)
        {
            if (! $this->confirm('This token does not look like sandbox. Continue anyway?', false))
            {
                return self::SUCCESS;
            }
        }

        $tag = trim((string) ($this->option('tag') ?? 'humano-sync-test'));
        $today = now()->toDateString();

        $customerId = $this->resolveCustomerId($client, $this->option('customer_id'));
        if ($customerId === null)
        {
            $this->error('No Cuéntica customer available. Pass --customer_id= or create a customer in sandbox first.');

            return self::FAILURE;
        }

        $providerId = $this->resolveProviderId($client, $this->option('provider_id'));
        if ($providerId === null)
        {
            $this->error('No Cuéntica provider available. Pass --provider_id= or create a provider in sandbox first.');

            return self::FAILURE;
        }

        $salePayload = [
            'issued' => true,
            'customer' => $customerId,
            'date' => $today,
            'description' => 'Humano sync test (venta) '.Carbon::now()->format('Y-m-d H:i'),
            'tags' => [$tag],
            'invoice_lines' => [[
                'quantity' => 1,
                'concept' => 'Servicio de prueba Humano',
                'amount' => 100,
                'discount' => 0,
                'tax' => 21,
                'sell_type' => 'service',
                'tax_regime' => '01',
                'tax_subjection_code' => 'S1',
            ]],
            'charges' => [[
                'date' => $today,
                'amount' => 121,
                'payment_method' => 'card',
                'paid' => true,
            ]],
        ];

        $serie = trim((string) $team->getSetting('cuentica_invoice_serie', ''));
        if ($serie !== '')
        {
            $salePayload['serie'] = $serie;
        }

        $sale = $client->createInvoice($salePayload);
        $this->info('Sale invoice created: id='.(string) Arr::get($sale, 'id').' number='.(string) Arr::get($sale, 'number'));

        $purchasePayload = [
            'date' => $today,
            'draft' => false,
            'provider' => $providerId,
            'document_type' => 'invoice',
            'document_number' => 'TEST-COMPRA-'.now()->format('YmdHis'),
            'tags' => [$tag],
            'annotations' => 'Humano sync test (compra)',
            'expense_lines' => [[
                'description' => 'Compra de prueba Humano',
                'base' => 50,
                'tax' => 21,
                'surcharge' => 0,
                'retention' => 0,
                'imputation' => 100,
                'expense_type' => 'other',
                'investment' => false,
            ]],
            'payments' => [[
                'date' => $today,
                'amount' => 60.5,
                'payment_method' => 'card',
                'paid' => true,
            ]],
            'vat_eu' => false,
        ];

        $purchase = $client->createExpense($purchasePayload);
        $this->info('Purchase expense created: id='.(string) Arr::get($purchase, 'id').' document_number='.(string) Arr::get($purchase, 'document_number'));

        $this->newLine();
        $this->line('Next steps:');
        $this->line("  php artisan cuentica:sync-invoices --team_id={$teamId} --mode=mutable --from={$today}");
        $this->line("  php artisan invoice-syncs:import-cuentica --team_id={$teamId} --fallback-tax-id --fallback-email --link-code-on-match");

        return self::SUCCESS;
    }

    private function resolveCustomerId($client, mixed $option): ?int
    {
        if (filled($option))
        {
            return (int) $option;
        }

        $customers = $client->listCustomers(['page_size' => 1]);
        $id = Arr::get($customers[0] ?? [], 'id');

        return $id !== null ? (int) $id : null;
    }

    private function resolveProviderId($client, mixed $option): ?int
    {
        if (filled($option))
        {
            return (int) $option;
        }

        $providers = $client->listProviders(['page_size' => 1]);
        $id = Arr::get($providers[0] ?? [], 'id');

        return $id !== null ? (int) $id : null;
    }
}
