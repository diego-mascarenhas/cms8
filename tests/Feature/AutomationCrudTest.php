<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_automation_list(): void
    {
        $user = $this->createAdminWithAutomationsModule();

        $this->actingAs($user)
            ->get(route('automation-list'))
            ->assertOk();
    }

    public function test_admin_can_create_automation(): void
    {
        $user = $this->createAdminWithAutomationsModule();

        $response = $this->actingAs($user)->post(route('automation.store'), [
            'name' => 'Soporte web',
            'slug' => 'soporte-web',
            'entry_prompt_key' => 'contacts:landing',
            'is_active' => '1',
            'channels' => [
                'api' => '1',
                'chat' => '1',
            ],
            'settings' => [
                'welcome_message' => 'Hola',
            ],
        ]);

        $automation = Automation::withoutGlobalScope('team')->where('slug', 'soporte-web')->first();
        $this->assertNotNull($automation);
        $response->assertRedirect(route('automation.show', $automation));
        $this->assertTrue($automation->allowsChannel(Automation::CHANNEL_API));
        $this->assertTrue($automation->allowsChannel(Automation::CHANNEL_CHAT));
        $this->assertFalse($automation->allowsChannel(Automation::CHANNEL_WHATSAPP));
        $this->assertSame('contacts:landing', $automation->entry_prompt_key);
        $this->assertNotEmpty($automation->public_token);
    }

    public function test_non_admin_cannot_create_automation(): void
    {
        $user = $this->createUserWithTeamAndRole('user');
        $this->enableAutomationsModule($user->currentTeam);

        $this->actingAs($user)
            ->post(route('automation.store'), [
                'name' => 'Blocked',
                'slug' => 'blocked',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_and_regenerate_token(): void
    {
        $user = $this->createAdminWithAutomationsModule();
        $automation = Automation::factory()->create([
            'team_id' => $user->current_team_id,
            'slug' => 'old-slug',
            'public_token' => 'oldtokenoldtokenoldtokenoldtokenoldtokenoldtokenoldtokenoldtoken12',
        ]);
        $oldToken = $automation->public_token;

        $this->actingAs($user)->put(route('automation.update', $automation), [
            'name' => 'Updated',
            'slug' => 'old-slug',
            'is_active' => '1',
            'channels' => ['api' => '1'],
            'regenerate_token' => '1',
        ])->assertRedirect(route('automation.show', $automation));

        $automation->refresh();
        $this->assertSame('Updated', $automation->name);
        $this->assertNotSame($oldToken, $automation->public_token);
    }

    private function createAdminWithAutomationsModule(): User
    {
        $user = $this->createUserWithTeamAndRole('admin');
        $this->enableAutomationsModule($user->currentTeam);

        return $user->refresh();
    }

    private function enableAutomationsModule(Team $team): void
    {
        Module::firstOrCreate(
            ['key' => 'automations'],
            [
                'name' => 'Automations',
                'description' => 'Automations',
                'is_core' => 0,
                'status' => 1,
                'order' => 0,
                'group' => 'automation',
            ],
        );
        $team->enableModule('automations');
    }

    private function createUserWithTeamAndRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        return $user->refresh();
    }
}
