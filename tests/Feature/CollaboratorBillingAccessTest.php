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
            ->assertForbidden();
    }

    public function test_collaborator_cannot_access_payments_income_or_expense_lists(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('income.index'))->assertForbidden();
        $this->actingAs($user)->get(route('expense.index'))->assertForbidden();
    }

    public function test_collaborator_cannot_access_fare_or_enterprise_lists(): void
    {
        $user = $this->makeCollaboratorUser();

        $this->actingAs($user)->get(route('fare.index'))->assertForbidden();
        $this->actingAs($user)->get(route('enterprise.index'))->assertForbidden();
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
}
