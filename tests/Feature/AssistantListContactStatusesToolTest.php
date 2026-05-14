<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Database\Seeders\ContactStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantListContactStatusesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_contact_statuses_returns_seeded_crm_status_names(): void
    {
        $this->seed(ContactStatusSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, null);

        $out = $service->execute('list_contact_statuses', []);

        $this->assertStringContainsString('CRM contact statuses', $out);
        $this->assertStringContainsString('Lead', $out);
        $this->assertStringContainsString('En seguimiento', $out);
        $this->assertStringContainsString('Conversión', $out);
        $this->assertStringContainsString('Perdido', $out);
        $this->assertStringContainsString('Cliente', $out);
        $this->assertStringContainsString('Finalizado', $out);
    }
}
