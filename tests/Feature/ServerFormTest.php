<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServerFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_store_sets_status_automatically_without_form_field(): void
    {
        [$user, $team] = $this->adminWithTeam();

        $response = $this->actingAs($user)->post(route('server.store'), [
            'name' => 'Huginn',
            'server_url' => 'huginn.example.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'auth_mode' => 'cpanel_user',
            'encrypted_token' => 'secret-password',
        ]);

        $server = Server::query()->where('server_url', 'huginn.example.test')->first();

        $response->assertRedirect(route('server.show', $server->id));
        $this->assertSame(ServerStatus::Unknown, $server->status_id);
        $this->assertSame($team->id, $server->team_id);
    }

    public function test_server_edit_form_does_not_show_status_field(): void
    {
        [$user, $team] = $this->adminWithTeam();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'whm.test',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => ServerStatus::Active->value,
        ]);

        $response = $this->actingAs($user)->get(route('server.edit', $server->id));

        $response->assertOk();
        $response->assertDontSee('name="status_id"', false);
        $response->assertDontSee('Status (*)', false);
    }

    public function test_server_create_form_shows_spanish_labels(): void
    {
        [$user, $team] = $this->adminWithTeam();

        $response = $this->actingAs($user)->get(route('server.create'));

        $response->assertOk();
        $response->assertSee('Servidores/', false);
        $response->assertSee('Crear', false);
        $response->assertSee('Nombre (*)', false);
        $response->assertSee('Dirección IP', false);
        $response->assertSee('URL del servidor (*)', false);
        $response->assertSee('Panel de control (*)', false);
        $response->assertSee('Contraseña (*)', false);
        $response->assertDontSee('id="auth_mode"', false);
        $response->assertDontSee('<textarea', false);
        $response->assertDontSee('Autenticación cPanel', false);
        $response->assertDontSee('id="team_id"', false);
        $response->assertDontSee('Sistema operativo', false);
        $response->assertDontSee('Manage your servers', false);
        $response->assertDontSee('Control Panel (*)', false);
    }

    public function test_server_store_ignores_submitted_team_id_and_uses_current_team(): void
    {
        [$user, $team] = $this->adminWithTeam();
        $otherTeam = Team::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('server.store'), [
            'name' => 'Muninn',
            'server_url' => 'muninn.example.test',
            'username' => 'root',
            'control_panel' => 'cpanel',
            'team_id' => $otherTeam->id,
        ]);

        $server = Server::withoutGlobalScopes()->where('server_url', 'muninn.example.test')->first();

        $this->assertNotNull($server);
        $this->assertSame($team->id, $server->team_id);
    }

    public function test_server_index_shows_spanish_table_labels(): void
    {
        [$user, $team] = $this->adminWithTeam();

        Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Huginn',
            'server_url' => 'huginn.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => ServerStatus::Active->value,
        ]);

        $response = $this->actingAs($user)->get(route('server.index'));

        $response->assertOk();
        $response->assertSee('Nombre', false);
        $response->assertSee('Dirección IP', false);
        $response->assertSee('Usuario', false);
        $response->assertSee('Estado', false);
        $response->assertSee('Acciones', false);
    }

    public function test_server_show_page_is_spanish_and_hides_operating_system(): void
    {
        [$user, $team] = $this->adminWithTeam();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Huginn',
            'server_url' => 'https//huginn.revisionalpha.cloud',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => ServerStatus::Active->value,
        ]);

        Domain::query()->create([
            'domain' => 'revisionalpha.es',
            'server_id' => $server->id,
            'username' => 'revision',
            'plan' => 'revision_enthusiast',
            'suspended' => false,
        ]);

        Domain::query()->create([
            'domain' => 'suspendido.test',
            'server_id' => $server->id,
            'username' => 'suspendido',
            'plan' => 'revision_standard',
            'suspended' => true,
        ]);

        $response = $this->actingAs($user)->get(route('server.show', $server->id));

        $response->assertOk();
        $response->assertSee('Servidores/', false);
        $response->assertSee('Información del servidor', false);
        $response->assertSee('Servidor', false);
        $response->assertSee('Panel', false);
        $response->assertSee('Actualización', false);
        $response->assertSee('huginn.revisionalpha.cloud', false);
        $response->assertSee('id="test-connection-btn"', false);
        $response->assertSee('id="sync-domains-btn"', false);
        $response->assertSee('id="server-domains-chart"', false);
        $response->assertSee('id="server-cpanel-domains-table"', false);
        $response->assertSee('Dominios en cPanel', false);
        $response->assertDontSee('URL del servidor', false);
        $response->assertDontSee('Panel de control', false);
        $response->assertDontSee('Última actualización', false);
        $response->assertDontSee('Operating System', false);
        $response->assertDontSee('Server Details', false);
        $response->assertDontSee('Back to Servers', false);
        $response->assertDontSee('sync-domains-action-btn', false);
    }

    public function test_server_store_normalizes_server_url_with_protocol(): void
    {
        [$user, $team] = $this->adminWithTeam();

        $this->actingAs($user)->post(route('server.store'), [
            'name' => 'Huginn',
            'server_url' => 'https://huginn.example.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
        ]);

        $this->assertDatabaseHas('servers', [
            'server_url' => 'huginn.example.test',
        ]);
    }

    public function test_server_show_datatable_returns_synced_domains(): void
    {
        [$user, $team] = $this->adminWithTeam();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Huginn',
            'server_url' => 'huginn.test',
            'username' => 'revision',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-token',
            'success' => true,
            'status_id' => ServerStatus::Active->value,
        ]);

        Domain::query()->create([
            'domain' => 'revisionalpha.es',
            'server_id' => $server->id,
            'username' => 'revision',
            'plan' => 'revision_enthusiast',
            'suspended' => false,
            'data' => ['disk_used' => 100, 'disk_limit' => 'unlimited'],
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('server.show', [
            'server' => $server->id,
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $response->assertJsonPath('recordsTotal', 1);
        $this->assertStringContainsString('revisionalpha.es', (string) $response->json('data.0.domain'));
    }

    /**
     * ServerController sits behind the `access-infrastructure-modules` gate, which reads a Spatie
     * role ({@see \App\Models\User::canAccessInfrastructure}), not the team pivot role.
     *
     * @return array{0: User, 1: Team}
     */
    private function adminWithTeam(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']));

        return [$user->refresh(), $team];
    }
}
