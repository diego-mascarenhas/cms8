<?php

namespace Tests\Feature;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\PaymentSync;
use App\Models\Team;
use App\Services\Billing\MercadoPagoSettlementPayerEnricher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BankStatementMercadoPagoIngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_enricher_persists_statement_lines_and_enriches_syncs(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('mercadopago_access_token', 'APP_USR-test-token', [
            'group' => 'mercadopago',
            'type' => 'password',
            'is_encrypted' => true,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '169690439304',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1060816,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1060816,
            'charge_created_at' => now()->subDay(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 169690439304,
                'operation_type' => 'account_fund',
            ],
        ]);

        $csv = implode("\n", [
            'SOURCE_ID;TRANSACTION_DATE;TRANSACTION_AMOUNT;PAYER_NAME;PAYER_ID_TYPE;PAYER_ID_NUMBER;PAYMENT_METHOD_TYPE;EXTERNAL_REFERENCE',
            '169690439304;2026-07-10T15:00:00.000-03:00;10608.16;Hygeia Sa;CUIT;30712345678;account_money;ref-1',
            '999;2026-07-11T15:00:00.000-03:00;100.00;Other;CUIT;20111111111;account_money;ref-2',
        ]);

        Http::fake([
            'api.mercadopago.com/v1/account/settlement_report/config' => Http::sequence()
                ->push([
                    'file_name_prefix' => 'humano-settlement-report',
                    'columns' => [
                        ['key' => 'SOURCE_ID'],
                        ['key' => 'PAYER_NAME'],
                        ['key' => 'PAYER_ID_TYPE'],
                        ['key' => 'PAYER_ID_NUMBER'],
                        ['key' => 'PAY_BANK_TRANSFER_ID'],
                        ['key' => 'TRANSACTION_DATE'],
                        ['key' => 'TRANSACTION_AMOUNT'],
                        ['key' => 'PAYMENT_METHOD_TYPE'],
                        ['key' => 'EXTERNAL_REFERENCE'],
                    ],
                ], 200)
                ->push(['ok' => true], 200),
            'api.mercadopago.com/v1/account/settlement_report' => Http::response([
                'id' => 1,
                'file_name' => 'humano-settlement-report-2026-07-25.csv',
            ], 202),
            'api.mercadopago.com/v1/account/settlement_report/list' => Http::response([
                [
                    'id' => 1,
                    'file_name' => 'humano-settlement-report-2026-07-25.csv',
                    'date_created' => '2026-07-25T12:00:00.000-03:00',
                ],
            ], 200),
            'api.mercadopago.com/v1/account/settlement_report/humano-settlement-report-2026-07-25.csv' => Http::response($csv, 200, [
                'Content-Type' => 'text/csv',
            ]),
        ]);

        $result = app(MercadoPagoSettlementPayerEnricher::class)->enrichTeam(
            $team,
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-25')->endOfDay(),
            dryRun: false,
            pollSeconds: 15,
        );

        $this->assertSame(1, $result['enriched']);
        $this->assertSame(2, $result['statement_lines']);

        $statement = BankStatement::query()
            ->where('team_id', $team->id)
            ->where('provider', BankStatement::PROVIDER_MERCADOPAGO)
            ->where('period_year', 2026)
            ->where('period_month', 7)
            ->first();

        $this->assertNotNull($statement);
        $this->assertSame(BankStatement::SOURCE_API, $statement->source);

        $line = BankStatementLine::query()
            ->where('bank_statement_id', $statement->id)
            ->where('external_id', '169690439304')
            ->first();

        $this->assertNotNull($line);
        $this->assertSame('Hygeia Sa', $line->payer_name);
        $this->assertSame('30712345678', $line->payer_id_number);
        $this->assertEqualsWithDelta(10608.16, (float) $line->amount, 0.01);
        $this->assertSame((int) $sync->id, (int) $line->payment_sync_id);

        $sync->refresh();
        $this->assertSame('Hygeia Sa', $sync->settlementPayerName());
    }
}
