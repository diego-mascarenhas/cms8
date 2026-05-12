<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnableCoreModulesForTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_team_does_not_enable_opt_out_core_modules_from_team_modules_config(): void
    {
        foreach (['dashboard', 'users', 'services', 'projects', 'notifications', 'tasks'] as $key)
        {
            Module::query()->create([
                'name' => ucfirst($key),
                'key' => $key,
                'icon' => 'layout',
                'description' => 'Test',
                'is_core' => true,
                'status' => 1,
            ]);
        }

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $this->assertTrue($team->hasModule('dashboard'));
        $this->assertFalse($team->hasModule('tasks'));
        $this->assertFalse($team->hasModule('users'));
        $this->assertFalse($team->hasModule('services'));
        $this->assertFalse($team->hasModule('projects'));
        $this->assertFalse($team->hasModule('notifications'));
    }

    public function test_new_team_does_not_enable_team_files_when_default_false(): void
    {
        Module::query()->create([
            'name' => 'Team files',
            'key' => 'team_files',
            'icon' => 'folders',
            'description' => 'Test',
            'is_core' => false,
            'status' => 1,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $this->assertFalse($team->fresh()->hasModule('team_files'));
    }

    public function test_new_team_does_not_enable_times_when_default_false(): void
    {
        Module::query()->create([
            'name' => 'Times',
            'key' => 'times',
            'icon' => 'clock',
            'description' => 'Test',
            'is_core' => false,
            'status' => 1,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $this->assertFalse($team->fresh()->hasModule('times'));
    }

    public function test_new_team_does_not_enable_templates_when_default_false(): void
    {
        Module::query()->create([
            'name' => 'Templates',
            'key' => 'templates',
            'icon' => 'template',
            'description' => 'Test',
            'is_core' => false,
            'group' => 'campaigns',
            'order' => 0,
            'status' => 1,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $this->assertFalse($team->fresh()->hasModule('templates'));
    }

    public function test_new_team_enables_team_files_when_default_true(): void
    {
        config(['team-modules.defaults.team_files' => true]);

        Module::query()->create([
            'name' => 'Team files',
            'key' => 'team_files',
            'icon' => 'folders',
            'description' => 'Test',
            'is_core' => false,
            'status' => 1,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $this->assertTrue($team->fresh()->hasModule('team_files'));
    }

    public function test_new_team_enables_invoices_and_skips_expenses_per_defaults(): void
    {
        foreach (['invoices', 'expenses'] as $key)
        {
            Module::query()->create([
                'name' => ucfirst($key),
                'key' => $key,
                'icon' => 'file',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ]);
        }

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $this->assertTrue($team->fresh()->hasModule('invoices'));
        $this->assertFalse($team->fresh()->hasModule('expenses'));
    }

    public function test_new_team_enables_financial_and_skips_ecommerce_modules_per_defaults(): void
    {
        foreach (['financial', 'products', 'orders', 'stores'] as $key)
        {
            Module::query()->create([
                'name' => ucfirst($key),
                'key' => $key,
                'icon' => 'package',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ]);
        }

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $team = $team->fresh();

        $this->assertTrue($team->hasModule('financial'));
        $this->assertFalse($team->hasModule('products'));
        $this->assertFalse($team->hasModule('orders'));
        $this->assertFalse($team->hasModule('stores'));
    }

    public function test_new_team_enables_performance_insights_addon_when_default_true(): void
    {
        Module::query()->create([
            'name' => 'Team performance insights',
            'key' => 'performance_insights',
            'icon' => 'chart-infographic',
            'description' => 'Test',
            'is_core' => false,
            'status' => 1,
            'order' => 10,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $this->assertTrue($team->fresh()->hasModule('performance_insights'));
    }
}
