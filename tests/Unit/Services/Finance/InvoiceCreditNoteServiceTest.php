<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\InvoiceCreditNoteService;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceCreditNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceCreditNoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $this->service = app(InvoiceCreditNoteService::class);
    }

    public function test_team_owner_can_issue_credit_note_for_stripe_invoice_with_secret(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $team->setSetting('stripe_secret', 'sk_test_example', [
            'type' => 'string',
            'group' => 'stripe',
            'is_encrypted' => false,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0200',
            'date' => now()->toDateString(),
            'gross_amount' => 50,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_eligible',
        ]);

        $this->assertTrue($this->service->canIssueCreditNote($user, $invoice));
        $this->assertTrue($this->service->canShowCreditNoteForm($user, $invoice));
    }

    public function test_team_owner_sees_form_without_stripe_secret_but_cannot_submit(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0201',
            'date' => now()->toDateString(),
            'gross_amount' => 50,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_no_secret',
        ]);

        $this->assertTrue($this->service->canShowCreditNoteForm($user, $invoice));
        $this->assertFalse($this->service->canIssueCreditNote($user, $invoice));
    }

    public function test_manual_invoice_cannot_issue_stripe_credit_note(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'MAN-001',
            'date' => now()->toDateString(),
            'gross_amount' => 50,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $this->assertFalse($this->service->canIssueCreditNote($user, $invoice));
    }
}
