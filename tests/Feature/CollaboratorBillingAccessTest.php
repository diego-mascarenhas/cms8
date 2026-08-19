<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollaboratorBillingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_collaborator_cannot_access_invoice_list(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->actingAs($user)
            ->get(route('invoice.index'))
            ->assertDeniedForBrowser();
    }

    public function test_collaborator_cannot_access_payments_income_or_expense_lists(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->actingAs($user)->get(route('payments.index'))->assertDeniedForBrowser();
        $this->actingAs($user)->get(route('income.index'))->assertDeniedForBrowser();
        $this->actingAs($user)->get(route('expense.index'))->assertDeniedForBrowser();
    }

    public function test_collaborator_cannot_access_subscription_or_finance_dashboard(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->actingAs($user)->get(route('subscription.index'))->assertDeniedForBrowser();
        $this->actingAs($user)->get(route('finance-dashboard.index'))->assertDeniedForBrowser();
        $this->actingAs($user)->get(route('billing.index'))->assertDeniedForBrowser();
    }

    public function test_collaborator_cannot_access_fare_or_enterprise_lists(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->actingAs($user)->get(route('fare.index'))->assertDeniedForBrowser();
        $this->actingAs($user)->get(route('enterprise.index'))->assertDeniedForBrowser();
    }

    public function test_collaborator_cannot_access_server_or_hosting(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->actingAs($user)->get(route('server.index'))->assertDeniedForBrowser();
        $this->actingAs($user)->get(route('hosting.index'))->assertDeniedForBrowser();
    }

    public function test_collaborator_cannot_access_infrastructure_gate(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->assertFalse($user->canAccessInfrastructure());
        $this->assertFalse(Gate::forUser($user)->allows('access-infrastructure-modules'));
    }

    public function test_collaborator_cannot_generate_project_budget_spec(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->actingAs($user)
            ->postJson(route('project.generate-budget-spec'), [
                'budget_given' => 'Presupuesto de prueba',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_access_invoice_list(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $user->switchTeam($user->currentTeam);

        $this->actingAs($user)
            ->get(route('invoice.index'))
            ->assertOk();
    }

    public function test_client_cannot_access_invoice_list(): void
    {
        $user = $this->makeClientUser();

        $this->actingAs($user)
            ->get(route('invoice.index'))
            ->assertDeniedForBrowser();
    }

    public function test_client_cannot_access_billing_per_model_and_gate(): void
    {
        $user = $this->makeClientUser();

        $this->assertFalse($user->canAccessBilling());
        $this->assertFalse(Gate::forUser($user)->allows('access-billing-modules'));
    }

    public function test_collaborator_cannot_access_billing_per_model_and_gate(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->assertFalse($user->canAccessBilling());
        $this->assertFalse(Gate::forUser($user)->allows('access-billing-modules'));
    }

    public function test_admin_can_access_billing_per_model_and_gate(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->canAccessBilling());
        $this->assertTrue(Gate::forUser($user)->allows('access-billing-modules'));
    }

    private function makeCollaboratorUser(): User
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('collaborator');
        $user->switchTeam($user->currentTeam);

        return $user;
    }

    private function makeClientUser(): User
    {
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('client');
        $user->switchTeam($user->currentTeam);

        return $user;
    }
}
