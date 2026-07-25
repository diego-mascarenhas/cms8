<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\User;
use App\Services\Billing\MercadoPagoAutoAssignMatcherService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MercadoPagoAutoAssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_index_shows_auto_assign_button(): void
    {
        [$user] = $this->makeAdminWithTeam();

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.index'))
            ->assertOk()
            ->assertSee(route('payments.syncs.mercadopago.auto-assign', ['rebuild' => 1]), false)
            ->assertSee(__('payment_sync.mercadopago.auto_assign.open'), false);
    }

    public function test_auto_assign_page_shows_suggestion_and_accepts_one_at_a_time(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Bratislava Marketing Group',
            'code' => 'cus_BRATISLAVA',
            'email' => 'finance@bratislava.test',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_bratislava_1',
            'customer_id' => 'cus_BRATISLAVA',
            'number' => '0005-0477',
            'status' => 'paid',
            'currency' => 'ars',
            'amount_due' => 6625.12,
            'amount_paid' => 6625.12,
            'amount_remaining' => 0,
            'total' => 6625.12,
            'paid' => true,
            'invoice_created_at' => now()->subDays(10),
            'last_synced_at' => now(),
            'raw_payload' => [
                'status_transitions' => [
                    'paid_at' => now()->subDays(2)->timestamp,
                ],
                'metadata' => [],
            ],
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0477',
            'date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'gross_amount' => 6625.12,
            'discount' => 0,
            'total_amount' => 6625.12,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_bratislava_1',
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-auto-1',
            'customer_id' => '999001',
            'customer_email' => 'finance@bratislava.test',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 662512,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 662512,
            'description' => 'Bank Transfer',
            'charge_created_at' => now()->subDays(2),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $suggestions = app(MercadoPagoAutoAssignMatcherService::class)
            ->buildSuggestions((int) $team->id, useAi: false);

        $this->assertCount(1, $suggestions);
        $this->assertSame((int) $sync->id, $suggestions[0]['sync_id']);
        $this->assertSame([(int) $invoice->id], $suggestions[0]['invoice_ids']);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.auto-assign', ['rebuild' => 1]))
            ->assertOk()
            ->assertSee(__('Back'), false)
            ->assertDontSee(__('payment_sync.mercadopago.back_payments'), false)
            ->assertSee('0005-0477', false)
            ->assertSee('Bratislava Marketing Group', false)
            ->assertSee(route('invoice.show', $invoice->id), false)
            ->assertSee(__('payment_sync.mercadopago.auto_assign.view_invoice'), false)
            ->assertSee(__('payment_sync.mercadopago.auto_assign.accept'), false);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.auto-assign.accept'))
            ->assertRedirect(route('payments.syncs.mercadopago.auto-assign'));

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', 'mp-auto-1')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame((int) $invoice->id, (int) $payment->invoice_id);
        $this->assertEqualsWithDelta(6625.12, (float) $payment->amount, 0.01);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.auto-assign'))
            ->assertOk()
            ->assertSee(__('payment_sync.mercadopago.auto_assign.done_title'), false);
    }

    public function test_auto_assign_skip_advances_without_importing(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Acme SL',
            'code' => 'cus_ACME',
            'email' => 'billing@acme.test',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_acme_1',
            'customer_id' => 'cus_ACME',
            'number' => '0005-1000',
            'status' => 'paid',
            'currency' => 'ars',
            'amount_due' => 100,
            'amount_paid' => 100,
            'amount_remaining' => 0,
            'total' => 100,
            'paid' => true,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'status_transitions' => ['paid_at' => now()->timestamp],
                'metadata' => [],
            ],
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-1000',
            'date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_acme_1',
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-skip-1',
            'customer_email' => 'billing@acme.test',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.auto-assign', ['rebuild' => 1]))
            ->assertOk()
            ->assertSee('0005-1000', false);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.auto-assign.skip'))
            ->assertRedirect(route('payments.syncs.mercadopago.auto-assign'));

        $this->assertFalse(
            Payment::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('source_reference_id', 'mp-skip-1')
                ->exists(),
        );

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.auto-assign'))
            ->assertOk()
            ->assertSee(__('payment_sync.mercadopago.auto_assign.done_title'), false);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team}
     */
    private function makeAdminWithTeam(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team];
    }
}
