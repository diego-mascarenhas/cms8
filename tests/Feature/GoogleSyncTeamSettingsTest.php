<?php

namespace Tests\Feature;

use App\Enums\ExternalProvider;
use App\Jobs\PushContactToGoogleJob;
use App\Jobs\SyncGoogleCalendarEventsJob;
use App\Jobs\SyncGoogleContactsJob;
use App\Jobs\SyncGoogleDataJob;
use App\Models\Contact;
use App\Models\ExternalAccount;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleSyncTeamSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
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

    public function test_google_sync_settings_page_shows_toggles(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'google']))
            ->assertOk()
            ->assertSee(__('app.team_setting_google_contacts_inbound_sync'), false)
            ->assertSee(__('app.team_setting_google_calendar_outbound_sync'), false);
    }

    public function test_google_sync_toggles_default_to_disabled(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->assertFalse($team->googleContactsInboundSyncEnabled());
        $this->assertFalse($team->googleCalendarInboundSyncEnabled());
        $this->assertFalse($team->googleContactsOutboundSyncEnabled());
        $this->assertFalse($team->googleCalendarOutboundSyncEnabled());
    }

    public function test_can_disable_google_contacts_inbound_sync_in_settings(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;

        $this->actingAs($user)->put(route('team-settings.update', $team), [
            'google' => [
                'google_contacts_inbound_sync_enabled' => '0',
                'google_calendar_inbound_sync_enabled' => '1',
                'google_contacts_outbound_sync_enabled' => '1',
                'google_calendar_outbound_sync_enabled' => '1',
            ],
        ])->assertRedirect();

        $team->refresh();
        $this->assertFalse($team->googleContactsInboundSyncEnabled());
        $this->assertTrue($team->googleCalendarInboundSyncEnabled());
    }

    public function test_queue_contacts_sync_skipped_when_inbound_disabled(): void
    {
        Bus::fake();
        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $team->setSetting('google_contacts_inbound_sync_enabled', false, [
            'group' => 'google',
            'type' => 'boolean',
        ]);

        ExternalAccount::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'provider' => ExternalProvider::Google,
            'provider_user_id' => 'google-'.Str::uuid()->toString(),
            'scopes' => [],
        ]);

        $this->actingAs($user)
            ->post(route('integrations.google.sync-contacts'))
            ->assertRedirect(route('integrations.google.synced-contacts'))
            ->assertSessionHas('warning');

        Bus::assertNotDispatched(SyncGoogleContactsJob::class);
    }

    public function test_contact_save_does_not_dispatch_push_when_outbound_disabled(): void
    {
        Bus::fake();
        config(['services.google.client_id' => 'test-client-id']);

        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $team->setSetting('google_contacts_outbound_sync_enabled', false, [
            'group' => 'google',
            'type' => 'boolean',
        ]);

        $this->actingAs($user);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Sync Test',
            'email' => 'sync-test@example.com',
            'phone' => '600000001',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => \App\Models\ContactStatus::query()->orderBy('id')->value('id'),
            'country' => 724,
            'language' => 'es',
        ]);

        Bus::assertNotDispatched(PushContactToGoogleJob::class);
    }

    public function test_sync_google_data_job_respects_inbound_toggles(): void
    {
        Bus::fake();

        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $team->setSetting('google_contacts_inbound_sync_enabled', false, [
            'group' => 'google',
            'type' => 'boolean',
        ]);
        $team->setSetting('google_calendar_inbound_sync_enabled', true, [
            'group' => 'google',
            'type' => 'boolean',
        ]);

        $account = ExternalAccount::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'provider' => ExternalProvider::Google,
            'provider_user_id' => 'google-'.Str::uuid()->toString(),
            'scopes' => [],
        ]);

        (new SyncGoogleDataJob($account->id))->handle();

        Bus::assertNotDispatched(SyncGoogleContactsJob::class);
        Bus::assertDispatched(SyncGoogleCalendarEventsJob::class);
    }
}
