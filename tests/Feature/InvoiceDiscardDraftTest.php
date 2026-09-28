<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\User;
use App\Services\Billing\StripeInvoiceDraftDiscardService;
use App\Services\Billing\TeamUsageInvoiceStripeGateway;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceDiscardDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_team_owner_sees_delete_draft_button_on_stripe_draft(): void
    {
        [$user, $invoice] = $this->createOwnerWithDraftInvoice();

        $this->actingAs($user)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee(__('Delete draft'), false)
            ->assertSee(route('invoice.discard-draft', $invoice), false);
    }

    public function test_non_owner_does_not_see_delete_draft_button(): void
    {
        [$owner, $invoice] = $this->createOwnerWithDraftInvoice();
        $team = $owner->currentTeam;

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'collaborator']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $member->assignRole('collaborator');

        $this->actingAs($member)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertDontSee(__('Delete draft'), false);
    }

    public function test_team_owner_can_discard_stripe_draft_from_both_sides(): void
    {
        [$user, $invoice] = $this->createOwnerWithDraftInvoice('in_discard_me');
        $team = $user->currentTeam;
        $team->setSetting('stripe_secret', 'sk_test_example', [
            'type' => 'string',
            'group' => 'stripe',
            'is_encrypted' => false,
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_discard_me',
            'raw_payload' => ['status' => 'draft', 'livemode' => false],
        ]);

        $invoicesApi = Mockery::mock();
        $invoicesApi->shouldReceive('delete')
            ->once()
            ->with('in_discard_me')
            ->andReturn((object) ['id' => 'in_discard_me', 'deleted' => true]);

        $client = Mockery::mock();
        $client->invoices = $invoicesApi;

        $this->partialMock(StripeInvoiceDraftDiscardService::class, function ($mock) use ($client): void
        {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('makeClient')
                ->once()
                ->andReturn($client);
        });

        // Re-bind with real gateway dependency but allow makeClient override via partialMock above.
        // The controller uses the container service; ensure our mock is used.
        $this->app->instance(
            StripeInvoiceDraftDiscardService::class,
            Mockery::mock(StripeInvoiceDraftDiscardService::class, [
                app(TeamUsageInvoiceStripeGateway::class),
            ])->makePartial()->shouldAllowMockingProtectedMethods()
        );

        $service = app(StripeInvoiceDraftDiscardService::class);
        $service->shouldReceive('makeClient')->once()->andReturn($client);

        $response = $this->actingAs($user)
            ->from(route('invoice.show', $invoice->id))
            ->post(route('invoice.discard-draft', $invoice));

        $response->assertRedirect(route('invoice.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoice_syncs', [
            'external_id' => 'in_discard_me',
            'provider' => 'stripe',
        ]);
    }

    public function test_non_owner_cannot_discard_draft(): void
    {
        [$owner, $invoice] = $this->createOwnerWithDraftInvoice();
        $team = $owner->currentTeam;

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $member->assignRole('admin');

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock): void
        {
            $mock->shouldNotReceive('deleteDraftInvoice');
        });

        $this->actingAs($member)
            ->from(route('invoice.show', $invoice->id))
            ->post(route('invoice.discard-draft', $invoice))
            ->assertRedirect(route('invoice.show', $invoice->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    /**
     * @return array{0: User, 1: Invoice}
     */
    private function createOwnerWithDraftInvoice(string $stripeId = 'in_test_draft_show'): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'DRAFT-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 9,
            'source_provider' => 'stripe',
            'source_reference_id' => $stripeId,
        ]);

        return [$user, $invoice];
    }
}
