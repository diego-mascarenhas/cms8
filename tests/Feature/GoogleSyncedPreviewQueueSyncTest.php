<?php

namespace Tests\Feature;

use App\Enums\ExternalProvider;
use App\Jobs\SyncGoogleCalendarEventsJob;
use App\Jobs\SyncGoogleContactsJob;
use App\Models\ExternalAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleSyncedPreviewQueueSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_contacts_sync_dispatches_one_job_per_google_account(): void
    {
        Bus::fake();

        [$user, $team] = $this->teamWithGoogleAccount();
        $team->setSetting('google_contacts_inbound_sync_enabled', '1');

        $this->actingAs($user)
            ->post(route('integrations.google.sync-contacts'))
            ->assertRedirect(route('integrations.google.synced-contacts'))
            ->assertSessionHas('status');

        Bus::assertDispatchedTimes(SyncGoogleContactsJob::class, 1);
        Bus::assertNotDispatched(SyncGoogleCalendarEventsJob::class);
    }

    public function test_queue_calendar_sync_dispatches_one_job_per_google_account(): void
    {
        Bus::fake();

        [$user, $team] = $this->teamWithGoogleAccount();
        $team->setSetting('google_calendar_inbound_sync_enabled', '1');

        $this->actingAs($user)
            ->post(route('integrations.google.sync-calendar'))
            ->assertRedirect(route('integrations.google.synced-calendar'))
            ->assertSessionHas('status');

        Bus::assertDispatchedTimes(SyncGoogleCalendarEventsJob::class, 1);
        Bus::assertNotDispatched(SyncGoogleContactsJob::class);
    }

    public function test_queue_contacts_sync_is_refused_when_inbound_sync_is_disabled(): void
    {
        Bus::fake();

        [$user] = $this->teamWithGoogleAccount();

        $this->actingAs($user)
            ->post(route('integrations.google.sync-contacts'))
            ->assertRedirect(route('integrations.google.synced-contacts'))
            ->assertSessionHas('warning');

        Bus::assertNotDispatched(SyncGoogleContactsJob::class);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function teamWithGoogleAccount(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        ExternalAccount::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'provider' => ExternalProvider::Google,
            'provider_user_id' => 'google-'.Str::uuid()->toString(),
            'scopes' => [],
        ]);

        return [$user->refresh(), $team];
    }
}
