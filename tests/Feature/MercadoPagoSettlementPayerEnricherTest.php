<?php

namespace Tests\Feature;

use App\Models\PaymentSync;
use App\Models\Team;
use App\Services\Billing\MercadoPagoSettlementPayerEnricher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoSettlementPayerEnricherTest extends TestCase
{
    use RefreshDatabase;

    public function test_enricher_maps_settlement_payer_fields_by_source_id(): void
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
                'payer' => [
                    'id' => 616106613,
                    'email' => 'diego.mascarenhas@icloud.com',
                ],
                'collector_id' => 616106613,
            ],
        ]);

        $csv = implode("\n", [
            'SOURCE_ID;PAYER_NAME;PAYER_ID_TYPE;PAYER_ID_NUMBER;PAY_BANK_TRANSFER_ID',
            '169690439304;Hygeia Sa;CUIT;30712345678;76V4MR2Z8P4VPR389DEZOL',
            '999;Other;CUIT;20111111111;AAAA',
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
                    'begin_date' => '2026-04-26T00:00:00Z',
                    'end_date' => '2026-07-25T23:59:59Z',
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
        $this->assertSame(2, $result['report_rows']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertSame(1, $result['chunks']);
        $this->assertSame(2, $result['statement_lines']);

        $sync->refresh();
        $this->assertSame('Hygeia Sa', $sync->settlementPayerName());
        $this->assertSame('CUIT', $sync->settlementPayerIdType());
        $this->assertSame('30712345678', $sync->settlementPayerIdNumber());
        $this->assertSame('Hygeia Sa', data_get($sync->raw_payload, 'settlement_payer.name'));
        $this->assertFalse($sync->lacksIdentifiablePayer());
        $this->assertSame('Hygeia Sa', $sync->displayPayerName());

        $this->assertDatabaseHas('bank_statement_lines', [
            'external_id' => '169690439304',
            'payer_name' => 'Hygeia Sa',
            'payment_sync_id' => $sync->id,
        ]);
    }

    public function test_parse_csv_by_source_id_supports_comma_delimiter(): void
    {
        $csv = "SOURCE_ID,PAYER_NAME,PAYER_ID_NUMBER\n123,Acme SA,20123456789\n";
        $parsed = app(MercadoPagoSettlementPayerEnricher::class)->parseCsvBySourceId($csv);

        $this->assertSame('Acme SA', $parsed['123']['PAYER_NAME']);
        $this->assertSame('20123456789', $parsed['123']['PAYER_ID_NUMBER']);
    }

    public function test_date_chunks_respect_sixty_day_api_limit(): void
    {
        $chunks = app(MercadoPagoSettlementPayerEnricher::class)->dateChunks(
            Carbon::parse('2026-04-26')->startOfDay(),
            Carbon::parse('2026-07-25')->endOfDay(),
        );

        $this->assertCount(4, $chunks);
        $this->assertSame('2026-04-26', $chunks[0][0]->toDateString());
        $this->assertSame('2026-05-25', $chunks[0][1]->toDateString());
        $this->assertSame('2026-05-26', $chunks[1][0]->toDateString());
        $this->assertSame('2026-07-25', $chunks[array_key_last($chunks)][1]->toDateString());
    }
}
