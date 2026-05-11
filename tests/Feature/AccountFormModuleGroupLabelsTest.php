<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountFormModuleGroupLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_and_support_group_titles_remain_english_when_locale_is_spanish(): void
    {
        app()->setLocale('es');

        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('account.edit', $team->id));

        $response->assertOk();
        $response->assertDontSee('id="module_accounting"', false);
        $response->assertDontSee('id="module_events"', false);
        $response->assertDontSee('Stripe billing', false);
        $response->assertDontSee('Events management module', false);
        $response->assertSee('<i class="ti ti-calculator me-2"></i>', false);
        $response->assertSee('Additional Modules', false);
        $response->assertSee('Accounting', false);
        $response->assertSee('Subscriptions, invoices, payments, affiliates and financial modules', false);
        $response->assertSee('Security', false);
        $response->assertSee('Support', false);
        $response->assertSee('Automation', false);
        $response->assertSee('Assistant instructions, funnel and API.', false);
        $response->assertSee('Marketing', false);
        $response->assertDontSee('Módulos adicionales', false);
        $response->assertDontSee('Seguridad', false);
    }

    public function test_account_update_preserves_hidden_modules_when_not_in_request(): void
    {
        $this->seed(ModuleSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'root', 'guard_name' => 'web'],
        );

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $team->enableModule('accounting');
        $team->enableModule('events');
        $team->enableModule('invoices');

        $coreKeys = Module::query()->where('is_core', true)->pluck('key')->all();

        $this->actingAs($user)->put(route('account.update', $team->id), [
            'name' => $team->name,
            'modules' => array_merge($coreKeys, ['invoices']),
        ]);

        $team->refresh();
        $this->assertTrue($team->hasModule('accounting'));
        $this->assertTrue($team->hasModule('events'));
        $this->assertTrue($team->hasModule('invoices'));
    }
}
