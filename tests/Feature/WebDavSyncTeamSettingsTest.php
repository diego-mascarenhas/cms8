<?php

namespace Tests\Feature;

use App\Enums\ExternalProvider;
use App\Jobs\SyncWebDavContactsJob;
use App\Jobs\SyncWebDavDataJob;
use App\Models\ExternalAccount;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebDavSyncTeamSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            TaskStatusSeeder::class,
        ]);

        Config::set('services.webdav.base_url', 'https://webdav.test');
        Config::set('services.webdav.api_token', 'test-token');
    }

    private function userWithTeam(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    public function test_webdav_sync_settings_page_shows_toggles(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'webdav']))
            ->assertOk()
            ->assertSee(__('app.team_setting_webdav_contacts_inbound_sync'), false)
            ->assertSee(__('app.team_setting_webdav_tasks_outbound_sync'), false);
    }

    public function test_webdav_sync_toggles_default_to_disabled(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->assertFalse($team->webdavContactsInboundSyncEnabled());
        $this->assertFalse($team->webdavCalendarInboundSyncEnabled());
        $this->assertFalse($team->webdavTasksInboundSyncEnabled());
    }

    public function test_webdav_create_form_is_accessible_without_api_token(): void
    {
        Config::set('services.webdav.api_token', null);

        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $team->forceFill(['name' => "REVISION ALPHA's Team"])->save();

        $response = $this->actingAs($user)
            ->get(route('integrations.webdav.create-form'));

        $response->assertOk()
            ->assertSee(__('app.webdav_create_account_title'), false)
            ->assertDontSee('REVISION ALPHA&amp;#039;s Team', false)
            ->assertSee('value="REVISION ALPHA&#039;s Team"', false);
    }

    public function test_can_create_webdav_account_from_settings_flow(): void
    {
        Http::fake([
            'https://webdav.test/api/users' => Http::response([
                'data' => [
                    'email' => 'team@example.com',
                    'dav_username' => 'team',
                    'name' => 'Team User',
                    'principal' => 'principals/team',
                    'dav_url' => 'https://webdav.test/dav/',
                    'password' => 'generated-password',
                ],
            ], 201),
        ]);

        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('integrations.webdav.create'), [
                'email' => 'team@example.com',
                'name' => 'Team User',
                'dav_username' => 'team',
                'password' => 'generated-password',
            ])
            ->assertRedirect(route('team-settings.index', $team))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('external_accounts', [
            'team_id' => $team->id,
            'provider' => ExternalProvider::WebDav->value,
            'provider_user_id' => 'team@example.com',
        ]);
    }

    public function test_can_disconnect_webdav_account(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        ExternalAccount::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'provider' => ExternalProvider::WebDav,
            'provider_user_id' => 'linked@example.com',
            'access_token' => encrypt('secret'),
        ]);

        $this->actingAs($user)
            ->delete(route('integrations.webdav.disconnect'))
            ->assertRedirect(route('team-settings.index', $team));

        $this->assertDatabaseMissing('external_accounts', [
            'team_id' => $team->id,
            'provider' => ExternalProvider::WebDav->value,
        ]);
    }

    public function test_sync_webdav_data_job_dispatches_enabled_resources(): void
    {
        Bus::fake();

        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $team->setSetting('webdav_contacts_inbound_sync_enabled', true, [
            'group' => 'webdav',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);
        $team->setSetting('webdav_tasks_inbound_sync_enabled', true, [
            'group' => 'webdav',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        $account = ExternalAccount::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'provider' => ExternalProvider::WebDav,
            'provider_user_id' => 'sync@example.com',
            'access_token' => encrypt('secret'),
        ]);

        (new SyncWebDavDataJob($account->id))->handle();

        Bus::assertDispatched(SyncWebDavContactsJob::class);
    }
}
