<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use App\Services\Billing\StripeCreditNoteMetadataWriter;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeCreditNoteMetadataWriterTest extends TestCase
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

    public function test_push_keeps_existing_stripe_metadata_and_adds_the_humano_number(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
        ]);

        $creditNote = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => 'CN-0005-0001',
            'date' => '2026-07-24',
            'gross_amount' => 100,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_meta_001',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'cn_meta_001',
            'number' => '0005-0396-CN-01',
            'status' => 'issued',
            'currency' => 'ars',
            'total' => 100,
            'paid' => true,
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 'cn_meta_001',
                'number' => '0005-0396-CN-01',
                'metadata' => [
                    'humano_invoice_id' => '58230',
                    'humano_team_id' => (string) $team->id,
                ],
            ],
        ]);

        $updates = new \ArrayObject;
        $creditNotesApi = new class($updates)
        {
            public function __construct(private \ArrayObject $updates) {}

            /**
             * @param  array<string, mixed>  $params
             */
            public function update(string $id, array $params = []): object
            {
                $this->updates->append(['id' => $id, 'params' => $params]);

                return (object) ['id' => $id];
            }
        };

        $client = new class('sk_test_fake', $creditNotesApi) extends StripeClient
        {
            public function __construct(string $secret, private object $creditNotesApi)
            {
                parent::__construct($secret);
            }

            public function __get($name)
            {
                return $this->creditNotesApi;
            }
        };

        $this->assertTrue(app(StripeCreditNoteMetadataWriter::class)->push($client, $creditNote));

        $this->assertSame('cn_meta_001', $updates[0]['id']);
        $this->assertSame('CN-0005-0001', $updates[0]['params']['metadata']['humano_credit_note_number']);

        $metadata = InvoiceSync::query()->where('external_id', 'cn_meta_001')->first()->raw_payload['metadata'];
        $this->assertSame('58230', $metadata['humano_invoice_id']);
        $this->assertSame('CN-0005-0001', $metadata['humano_credit_note_number']);
        $this->assertSame('0005-0396-CN-01', InvoiceSync::query()->where('external_id', 'cn_meta_001')->value('number'));
    }

    public function test_dry_run_lists_quarter_notes_without_calling_stripe(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => 'CN-0005-0001',
            'date' => '2026-07-24',
            'gross_amount' => 10,
            'total_amount' => 10,
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_dry_001',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => '0005-0100-CN-01',
            'date' => '2026-06-01',
            'gross_amount' => 10,
            'total_amount' => 10,
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_old_001',
        ]);

        $this->artisan('invoices:push-credit-note-metadata', [
            '--team_id' => $team->id,
            '--from' => '2026-07-01',
            '--to' => '2026-10-01',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('CN-0005-0001 → cn_dry_001')
            ->doesntExpectOutputToContain('0005-0100-CN-01')
            ->assertSuccessful();
    }
}
