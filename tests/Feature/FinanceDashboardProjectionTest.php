<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceDashboardProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_projection_page_requires_authentication(): void
    {
        $this->get(route('finance-dashboard.projection'))
            ->assertRedirect();
    }

    public function test_authenticated_user_can_view_projection_report(): void
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

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'S-100',
            'date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(15)->toDateString(),
            'gross_amount' => 500,
            'discount' => 0,
            'total_amount' => 500,
            'balance' => 0,
            'status' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('finance-dashboard.projection'))
            ->assertOk()
            ->assertSee(__('Financial projection report'), false);
    }
}
