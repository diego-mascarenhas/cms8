<?php

namespace Tests\Feature;

use App\Enums\ExternalProvider;
use App\Jobs\SyncGoogleCalendarEventsJob;
use App\Jobs\SyncGoogleContactsJob;
use App\Models\ExternalAccount;
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
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Bus::fake();

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

        $this->actingAs($user)
            ->post(route('integrations.google.sync-contacts'))
            ->assertRedirect(route('integrations.google.synced-contacts'))
            ->assertSessionHas('status');

        Bus::assertDispatchedTimes(SyncGoogleContactsJob::class, 1);
        Bus::assertNotDispatched(SyncGoogleCalendarEventsJob::class);
    }

    public function test_queue_calendar_sync_dispatches_one_job_per_google_account(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Bus::fake();

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

        $this->actingAs($user)
            ->post(route('integrations.google.sync-calendar'))
            ->assertRedirect(route('integrations.google.synced-calendar'))
            ->assertSessionHas('status');

        Bus::assertDispatchedTimes(SyncGoogleCalendarEventsJob::class, 1);
        Bus::assertNotDispatched(SyncGoogleContactsJob::class);
    }
}
