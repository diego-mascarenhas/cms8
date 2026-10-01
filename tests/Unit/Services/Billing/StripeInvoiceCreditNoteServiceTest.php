<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Team;
use App\Services\Billing\StripeCreditNoteCoreImportService;
use App\Services\Billing\StripeCreditNoteCreatePayloadBuilder;
use App\Services\Billing\StripeInvoiceCreditNoteService;
use App\Services\Billing\StripeInvoiceSyncRefresher;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeInvoiceCreditNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_issuing_a_credit_note_leaves_the_original_invoice_unchanged(): void
    {
        $team = Team::factory()->create();
        $team->setSetting('stripe_secret', 'sk_test_example', [
            'type' => 'string',
            'group' => 'stripe',
            'is_encrypted' => false,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente ES',
            'code' => 'cus_keep_original',
            'country' => 'ES',
        ]);

        $original = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0989',
            'date' => '2026-05-01',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_keep_original',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $original->id,
            'description' => 'Servicio',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        $rewrite = InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_keep_original',
            'customer_id' => 'cus_keep_original',
            'number' => 'REWRITE-0005',
            'status' => 'paid',
            'currency' => 'eur',
            'subtotal' => 1,
            'total' => 1,
            'amount_due' => 0,
            'amount_paid' => 1,
            'amount_remaining' => 0,
            'paid' => true,
            'invoice_created_at' => '2026-02-01 00:00:00',
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 'in_keep_original',
                'lines' => [
                    'data' => [
                        [
                            'description' => 'Rewritten line',
                            'quantity' => 1,
                            'amount_excluding_tax' => 100,
                        ],
                    ],
                ],
            ],
        ]);

        $creditNote = new class
        {
            public string $id = 'cn_keep_original';

            public string $number = '0005-0990';

            public int $amount = 12100;

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return [
                    'id' => 'cn_keep_original',
                    'number' => '0005-0990',
                    'status' => 'issued',
                    'created' => strtotime('2026-05-10 12:00:00'),
                    'subtotal' => 10000,
                    'total' => 12100,
                    'amount' => 12100,
                    'tax' => 2100,
                    'currency' => 'eur',
                    'memo' => 'Abono factura 0005-0989',
                    'lines' => [
                        'data' => [
                            [
                                'description' => 'Devolución servicio',
                                'quantity' => 1,
                                'amount_excluding_tax' => 10000,
                                'amount' => 10000,
                                'tax_amounts' => [
                                    ['amount' => 2100],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        };

        $invoicesApi = new class
        {
            public function retrieve(string $id, array $params = []): object
            {
                return (object) ['id' => $id];
            }
        };

        $creditNotesApi = new class($creditNote)
        {
            public function __construct(private object $creditNote) {}

            /**
             * @param  array<string, mixed>  $params
             */
            public function create(array $params): object
            {
                return $this->creditNote;
            }
        };

        $client = new class('sk_test_fake', $invoicesApi, $creditNotesApi) extends StripeClient
        {
            public function __construct(
                string $secret,
                private object $invoicesApi,
                private object $creditNotesApi,
            ) {
                parent::__construct($secret);
            }

            public function __get($name)
            {
                return $name === 'creditNotes' ? $this->creditNotesApi : $this->invoicesApi;
            }
        };

        $refresher = $this->createMock(StripeInvoiceSyncRefresher::class);
        $refresher->expects($this->once())
            ->method('refreshFromStripe')
            ->willReturn($rewrite);

        $payloadBuilder = $this->createMock(StripeCreditNoteCreatePayloadBuilder::class);
        $payloadBuilder->expects($this->once())
            ->method('build')
            ->willReturn([
                'invoice' => 'in_keep_original',
                'amount' => 12100,
            ]);

        $service = new class($refresher, app(StripeCreditNoteCoreImportService::class), $payloadBuilder, $client) extends StripeInvoiceCreditNoteService
        {
            public function __construct(
                StripeInvoiceSyncRefresher $refresher,
                StripeCreditNoteCoreImportService $creditNoteImporter,
                StripeCreditNoteCreatePayloadBuilder $payloadBuilder,
                private readonly StripeClient $client,
            ) {
                parent::__construct($refresher, $creditNoteImporter, $payloadBuilder);
            }

            protected function makeStripeClient(string $secret): StripeClient
            {
                return $this->client;
            }
        };

        $result = $service->issueForInvoice($original, 'duplicate');

        $original->refresh();
        $original->load('items');

        $this->assertSame('0005-0989', $original->number);
        $this->assertSame('2026-05-01', Carbon::parse($original->date)->toDateString());
        $this->assertSame(100.0, (float) $original->gross_amount);
        $this->assertSame(121.0, (float) $original->total_amount);
        $this->assertSame(2, (int) $original->status);
        $this->assertCount(1, $original->items);
        $this->assertSame(100.0, (float) $original->items->first()->unit_price);
        $this->assertSame(21.0, (float) $original->items->first()->tax_percentage);

        $abono = Invoice::withoutGlobalScopes()
            ->where('source_reference_id', 'cn_keep_original')
            ->first();

        $this->assertInstanceOf(Invoice::class, $abono);
        $this->assertNotSame($original->id, $abono->id);
        $this->assertSame(4, (int) $abono->status);
        $this->assertSame(100.0, (float) $abono->gross_amount);
        $this->assertSame(121.0, (float) $abono->total_amount);
        $this->assertSame('cn_keep_original', $result['credit_note_id']);
    }
}
