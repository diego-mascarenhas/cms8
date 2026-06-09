<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\StripeCreditNoteCreatePayloadBuilder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeCreditNoteCreatePayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    private StripeCreditNoteCreatePayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        $this->builder = app(StripeCreditNoteCreatePayloadBuilder::class);
    }

    public function test_it_builds_credit_note_lines_from_stripe_invoice_line_items(): void
    {
        $invoice = $this->makeInvoice();

        $payload = $this->builder->build(
            'in_test_credit_note',
            $invoice,
            'order_change',
            [
                'currency' => 'eur',
                'lines' => [
                    'data' => [[
                        'id' => 'il_test_line_1',
                        'quantity' => 1,
                    ]],
                ],
            ],
        );

        $this->assertSame('in_test_credit_note', $payload['invoice']);
        $this->assertSame('order_change', $payload['reason']);
        $this->assertSame([
            [
                'type' => 'invoice_line_item',
                'invoice_line_item' => 'il_test_line_1',
                'quantity' => 1,
            ],
        ], $payload['lines']);
        $this->assertArrayNotHasKey('amount', $payload);
    }

    public function test_it_falls_back_to_post_payment_credit_note_amount_when_lines_are_missing(): void
    {
        $invoice = $this->makeInvoice();

        $payload = $this->builder->build(
            'in_test_credit_note',
            $invoice,
            'duplicate',
            [
                'currency' => 'eur',
                'post_payment_credit_notes_amount' => 349,
                'total' => 349,
                'lines' => ['data' => []],
            ],
        );

        $this->assertSame(349, $payload['amount']);
        $this->assertArrayNotHasKey('lines', $payload);
    }

    private function makeInvoice(): Invoice
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        return Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0100',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 4.99,
            'discount' => 1.5,
            'total_amount' => 3.49,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_credit_note',
        ]);
    }
}
