<?php

namespace Tests\Feature;

use App\Enums\ExternalProvider;
use App\Jobs\SyncWebDavTasksJob;
use App\Models\ExternalAccount;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskSyncMapping;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebDavTaskInboundSyncTest extends TestCase
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
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    private function externalAccount(User $user): ExternalAccount
    {
        $team = $user->currentTeam;
        $this->assertNotNull($team);

        $team->setSetting('webdav_tasks_inbound_sync_enabled', true, [
            'group' => 'webdav',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        return ExternalAccount::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'provider' => ExternalProvider::WebDav,
            'provider_user_id' => 'sync@example.com',
            'access_token' => encrypt('secret'),
        ]);
    }

    public function test_inbound_sync_preserves_in_progress_when_reminder_is_not_completed(): void
    {
        $user = $this->userWithTeam();
        $account = $this->externalAccount($user);
        $inProgressStatusId = (int) TaskStatus::query()->where('name', 'IN_PROGRESS')->value('id');

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $account->team_id,
            'title' => 'Phone reminder task',
            'status_id' => $inProgressStatusId,
            'responsible_id' => $user->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        TaskSyncMapping::query()->create([
            'external_account_id' => $account->id,
            'task_id' => $task->id,
            'external_id' => 'vtodo-uid-1',
            'last_synced_at' => now()->subHour(),
        ]);

        Http::fake([
            'https://webdav.test/api/tasks*' => Http::response([
                'data' => [[
                    'uid' => 'vtodo-uid-1',
                    'summary' => 'Phone reminder task',
                    'description' => null,
                    'due_at' => now()->addDay()->toIso8601String(),
                    'completed' => false,
                    'updated_at' => now()->subMinutes(5)->getTimestamp(),
                ]],
            ]),
        ]);

        (new SyncWebDavTasksJob($account->id))->handle(app(\App\Sync\Providers\WebDavTaskSyncProvider::class));

        $this->assertSame($inProgressStatusId, (int) Task::withoutGlobalScopes()->findOrFail($task->id)->status_id);
    }

    public function test_inbound_sync_marks_done_when_reminder_is_completed_on_phone(): void
    {
        $user = $this->userWithTeam();
        $account = $this->externalAccount($user);
        $inProgressStatusId = (int) TaskStatus::query()->where('name', 'IN_PROGRESS')->value('id');
        $doneStatusId = (int) TaskStatus::query()->where('name', 'DONE')->value('id');

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $account->team_id,
            'title' => 'Finish on phone',
            'status_id' => $inProgressStatusId,
            'responsible_id' => $user->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        TaskSyncMapping::query()->create([
            'external_account_id' => $account->id,
            'task_id' => $task->id,
            'external_id' => 'vtodo-uid-2',
            'last_synced_at' => now()->subHour(),
        ]);

        Http::fake([
            'https://webdav.test/api/tasks*' => Http::response([
                'data' => [[
                    'uid' => 'vtodo-uid-2',
                    'summary' => 'Finish on phone',
                    'description' => null,
                    'due_at' => now()->addDay()->toIso8601String(),
                    'completed' => true,
                    'updated_at' => now()->getTimestamp(),
                ]],
            ]),
        ]);

        (new SyncWebDavTasksJob($account->id))->handle(app(\App\Sync\Providers\WebDavTaskSyncProvider::class));

        $this->assertSame($doneStatusId, (int) Task::withoutGlobalScopes()->findOrFail($task->id)->status_id);
    }
}
