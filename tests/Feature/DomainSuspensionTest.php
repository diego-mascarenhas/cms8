<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_suspension_suspends_account_on_whm_and_updates_domain(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/suspendacct*' => Http::response([
                'result' => [
                    ['status' => 1, 'statusmsg' => 'Account Suspended'],
                ],
            ]),
        ]);

        [$user, $team] = $this->infrastructureAdmin();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'cpanel.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create([
            'server_id' => $server->id,
            'username' => 'siteuser',
            'suspended' => false,
        ]);

        $response = $this->actingAs($user)->post(route('domain.toggle-suspension', $domain->id));

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $this->assertTrue($domain->fresh()->suspended);

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), 'suspendacct')
                && ($request['user'] ?? null) === 'siteuser';
        });
    }

    public function test_toggle_suspension_unsuspends_account_on_whm(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/unsuspendacct*' => Http::response([
                'result' => [
                    ['status' => 1, 'statusmsg' => 'Account Unsuspended'],
                ],
            ]),
        ]);

        [$user, $team] = $this->infrastructureAdmin();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'cpanel.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create([
            'server_id' => $server->id,
            'username' => 'siteuser',
            'suspended' => true,
        ]);

        $response = $this->actingAs($user)->post(route('domain.toggle-suspension', $domain->id));

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $this->assertFalse($domain->fresh()->suspended);

        Http::assertSent(function ($request)
        {
            return str_contains($request->url(), 'unsuspendacct')
                && ($request['user'] ?? null) === 'siteuser';
        });
    }

    public function test_toggle_suspension_shows_error_when_whm_rejects_request(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/suspendacct*' => Http::response([
                'result' => [
                    ['status' => 0, 'statusmsg' => 'Unable to suspend account'],
                ],
            ]),
        ]);

        [$user, $team] = $this->infrastructureAdmin();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'cpanel.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create([
            'server_id' => $server->id,
            'username' => 'siteuser',
            'suspended' => false,
        ]);

        $response = $this->actingAs($user)->post(route('domain.toggle-suspension', $domain->id));

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('error');
        $this->assertFalse($domain->fresh()->suspended);
    }

    public function test_domain_show_displays_suspended_notice_without_calling_cpanel(): void
    {
        Http::fake();

        [$user, $team] = $this->infrastructureAdmin();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'cpanel.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create([
            'server_id' => $server->id,
            'username' => 'siteuser',
            'suspended' => true,
        ]);

        $response = $this->actingAs($user)->get(route('domain.show', $domain->id));

        $response->assertOk();
        $response->assertSee('La cuenta está suspendida en cPanel', false);
        $response->assertDontSee('RAWFETCH::fetch is not allowed', false);
        Http::assertNothingSent();
    }

    public function test_domain_refresh_marks_domain_suspended_when_cpanel_returns_suspended_error(): void
    {
        $suspendedError = 'Failed to parse adminbin request: Cpanel::RAWFETCH::fetch is not allowed for suspended accounts';

        Http::fake([
            'https://cpanel.test:2087/json-api/listaccts*' => Http::response([
                'result' => [
                    [
                        'domain' => 'example.test',
                        'user' => 'siteuser',
                        'plan' => 'default',
                        'suspended' => 0,
                    ],
                ],
            ]),
            'https://cpanel.test:2087/*' => Http::response([
                'result' => [
                    'status' => 0,
                    'errors' => [$suspendedError],
                ],
            ]),
            'https://cpanel.test:2083/*' => Http::response([
                'status' => 0,
                'errors' => [$suspendedError],
            ]),
        ]);

        [$user, $team] = $this->infrastructureAdmin();

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'cpanel.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'example.test',
            'server_id' => $server->id,
            'username' => 'siteuser',
            'suspended' => false,
        ]);

        $response = $this->actingAs($user)->post(route('domain.refresh', $domain->id));

        $response->assertRedirect(route('domain.show', $domain->id));
        $response->assertSessionHas('success');
        $this->assertTrue($domain->fresh()->suspended);
    }

    /**
     * Domains live behind the `access-infrastructure-modules` gate, which reads a Spatie role
     * ({@see \App\Models\User::canAccessInfrastructure}), not the team pivot role.
     *
     * @return array{0: User, 1: Team}
     */
    private function infrastructureAdmin(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        return [$user->refresh(), $team];
    }
}
