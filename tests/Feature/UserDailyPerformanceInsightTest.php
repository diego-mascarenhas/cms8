<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use App\Services\DailyTeamDigestMetricsCollector;
use App\Services\UserDailyPerformanceInsightService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseDepartmentSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SourceSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDailyPerformanceInsightTest extends TestCase
{
    use RefreshDatabase;

    private function seedInsightDependencies(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            SourceSeeder::class,
            TaskStatusSeeder::class,
            EnterpriseDepartmentSeeder::class,
        ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => 'editor']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);
        $this->enablePerformanceInsightsModule($team);

        return $user->refresh();
    }

    private function performanceInsightsDataTableUrl(): string
    {
        $columns = [];
        foreach ([
            ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'insight_date', 'name' => 'insight_date', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'user_name', 'name' => 'user_name', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'performance_ratio', 'name' => 'performance_ratio', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'headline', 'name' => 'headline', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'focus', 'name' => 'focus', 'searchable' => 'true', 'orderable' => 'false'],
            ['data' => 'message', 'name' => 'message', 'searchable' => 'true', 'orderable' => 'false'],
        ] as $def)
        {
            $columns[] = array_merge($def, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return route('performance-insights.index').'?'.http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'desc']],
            'columns' => $columns,
        ]);
    }

    private function enablePerformanceInsightsModule(Team $team): void
    {
        Module::firstOrCreate(
            ['key' => 'performance_insights'],
            ['name' => 'Team performance insights', 'is_core' => false],
        );
        $team->enableModule('performance_insights');
    }

    public function test_digest_collector_includes_enabled_module_sections(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        Module::firstOrCreate(['key' => 'chat'], ['name' => 'Chat', 'is_core' => false]);
        Module::firstOrCreate(['key' => 'tasks'], ['name' => 'Tasks', 'is_core' => false]);
        $team->enableModule('chat');
        $team->enableModule('tasks');
        $team = $team->fresh();

        $digest = app(DailyTeamDigestMetricsCollector::class)->collect($user, $team);

        $this->assertSame(DailyTeamDigestMetricsCollector::DIGEST_VERSION, $digest['digest_version']);
        $this->assertArrayHasKey('user_activity', $digest);
        $this->assertArrayHasKey('whatsapp', $digest);
        $this->assertArrayHasKey('tasks', $digest);
        $this->assertArrayHasKey('highlights', $digest);
        $this->assertIsArray($digest['highlights']);
    }

    public function test_insight_snapshot_stores_digest_version_after_generation(): void
    {
        $this->seedInsightDependencies();
        config(['daily_performance_insight.use_llm' => false]);
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        $insight = app(UserDailyPerformanceInsightService::class)->ensureTodayRecord($user, $team, null);

        $this->assertSame(
            DailyTeamDigestMetricsCollector::DIGEST_VERSION,
            $insight->context_snapshot['digest_version'] ?? null,
        );
        $this->assertArrayHasKey('highlights', $insight->context_snapshot);
    }

    public function test_performance_insights_index_forbidden_for_collaborator(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('collaborator');

        $response = $this->actingAs($user)->get(route('performance-insights.index'));

        $response->assertForbidden();
    }

    public function test_performance_insights_index_ok_for_admin(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->get(route('performance-insights.index'));

        $response->assertOk();
        $response->assertSee('user-daily-performance-insights-table', false);
        $response->assertSee('performance-insights/list', false);
    }

    public function test_performance_insights_datatable_returns_rows_via_ajax(): void
    {
        $this->seedInsightDependencies();
        config(['daily_performance_insight.use_llm' => false]);
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        app(UserDailyPerformanceInsightService::class)->ensureTodayRecord($user, $team, null);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->performanceInsightsDataTableUrl());

        $response->assertOk();
        $response->assertJsonPath('recordsTotal', 1);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_generate_command_creates_in_app_notification_for_recipient(): void
    {
        $this->seedInsightDependencies();
        $admin = $this->createUserWithRole('admin');
        $team = $admin->currentTeam;

        Mail::fake();
        config([
            'daily_performance_insight.send_email' => false,
            'daily_performance_insight.use_llm' => false,
        ]);
        $team->setSetting('performance_insights_in_app_notification', true, [
            'group' => 'notifications',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        $this->artisan('performance-insights:generate')->assertSuccessful();

        $insight = UserDailyPerformanceInsight::query()
            ->where('team_id', $team->id)
            ->where('user_id', $admin->id)
            ->first();

        $this->assertNotNull($insight);
        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'reference' => $insight->id,
            'is_read' => false,
        ]);

        $this->actingAs($admin);
        $this->assertEquals(
            1,
            Notification::query()->forRecipientUser($admin->id)->unread()->count(),
        );

        $this->artisan('performance-insights:generate')->assertSuccessful();
        $this->assertEquals(
            1,
            Notification::withoutGlobalScopes()->where('reference', $insight->id)->count(),
        );
    }

    public function test_generate_command_skips_in_app_notification_when_team_setting_disabled(): void
    {
        $this->seedInsightDependencies();
        $admin = $this->createUserWithRole('admin');
        $team = $admin->currentTeam;

        config([
            'daily_performance_insight.send_email' => false,
            'daily_performance_insight.use_llm' => false,
        ]);
        $team->setSetting('performance_insights_in_app_notification', false, [
            'group' => 'notifications',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        $this->artisan('performance-insights:generate')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_performance_insights_generate_command_only_targets_admin_and_root(): void
    {
        $this->seedInsightDependencies();
        $admin = $this->createUserWithRole('admin');
        $team = $admin->currentTeam;

        $collaborator = User::factory()->create();
        $collaborator->assignRole(Role::firstOrCreate(['name' => 'collaborator']));
        $collaborator->teams()->attach($team->id, ['role' => 'editor']);
        $collaborator->current_team_id = $team->id;
        $collaborator->save();

        Mail::fake();
        config(['daily_performance_insight.send_email' => true]);

        $this->artisan('performance-insights:generate')->assertSuccessful();

        $this->assertDatabaseHas('user_daily_performance_insights', [
            'team_id' => $team->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('user_daily_performance_insights', [
            'team_id' => $team->id,
            'user_id' => $collaborator->id,
        ]);

        $this->artisan('performance-insights:generate')->assertSuccessful();
        $this->assertEquals(1, UserDailyPerformanceInsight::query()->where('team_id', $team->id)->count());
        Mail::assertSent(\App\Mail\DailyPerformanceInsightMail::class);
    }

    public function test_generate_command_skips_teams_without_performance_insights_module(): void
    {
        $this->seedInsightDependencies();
        $admin = $this->createUserWithRole('admin');
        $team = $admin->currentTeam;
        $team->modules()->detach(
            Module::where('key', 'performance_insights')->value('id'),
        );

        $this->artisan('performance-insights:generate')->assertSuccessful();

        $this->assertDatabaseMissing('user_daily_performance_insights', [
            'team_id' => $team->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_dashboard_does_not_persist_daily_insight_and_shows_assistant_prompt(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        $this->actingAs($user);
        app()->setLocale('en');

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $this->assertDatabaseMissing('user_daily_performance_insights', [
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $firstName = explode(' ', (string) $user->name, 2)[0];
        $response->assertSee(e(__('app.dashboard_assistant_greeting', ['name' => $firstName])), false);
        $response->assertSee(e(__('app.dashboard_assistant_subtitle')), false);
        $response->assertSee(__('app.dashboard_open_assistant'), false);
        $response->assertSee('data-bs-target="#assistant-offcanvas"', false);
    }

    public function test_find_today_insight_returns_null_without_row(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        $service = app(UserDailyPerformanceInsightService::class);
        $this->assertNull($service->findTodayInsight($user, $team));
    }

    public function test_insight_service_first_or_create_is_idempotent_per_day(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        $service = app(UserDailyPerformanceInsightService::class);
        $first = $service->ensureTodayRecord($user, $team, 'Tu dossier comercial');
        $second = $service->ensureTodayRecord($user, $team, 'Other phase label');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->headline, $second->headline);
        $this->assertSame('Tu dossier comercial', $first->context_snapshot['mentoring_phase_label'] ?? null);
        $this->assertEquals(1, UserDailyPerformanceInsight::query()->count());
    }

    public function test_insight_service_uses_llm_when_enabled_and_response_valid(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        config(['daily_performance_insight.use_llm' => true]);
        $longMessage = 'Your last seven days show steady client touchpoints and completed tasks. '
            .'Use that momentum to tighten follow-ups on the hottest opportunities before month end.';
        AnonymousAgent::fake([json_encode([
            'headline' => 'Glow✨',
            'focus' => "one two three\nfour five",
            'message' => $longMessage,
        ], JSON_UNESCAPED_UNICODE)]);

        $service = app(UserDailyPerformanceInsightService::class);
        $insight = $service->ensureTodayRecord($user, $team, null, null, false, 'en');

        $this->assertSame('llm', $insight->context_snapshot['insight_source'] ?? null);
        $this->assertSame('Glow✨', $insight->headline);
        $this->assertSame("one two three\nfour five", $insight->focus);
        $this->assertSame($longMessage, $insight->message);
        $this->assertCount(1, preg_split('/\s+/u', $insight->headline, -1, PREG_SPLIT_NO_EMPTY));
        $focusWords = preg_split('/\s+/u', str_replace("\n", ' ', $insight->focus), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $this->assertCount(5, $focusWords);
    }

    public function test_insight_service_falls_back_when_llm_returns_invalid_json(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        config(['daily_performance_insight.use_llm' => true]);
        AnonymousAgent::fake(['not valid json at all']);

        $service = app(UserDailyPerformanceInsightService::class);
        $insight = $service->ensureTodayRecord($user, $team, null, null, false, 'en');

        $this->assertSame('emergency', $insight->context_snapshot['insight_source'] ?? null);
        $this->assertSame(trans('app.performance_insight_emergency_headline_low', [], 'en'), $insight->headline);
        $this->assertNotSame('', trim($insight->focus));
        $this->assertStringContainsString('your commercial dossier', $insight->message);
    }

    public function test_insight_service_upgrades_legacy_long_row_to_micro_format(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        $longMessage = (string) __('app.performance_insight_message_tier_low', [
            'interactions' => 0,
            'minutes' => 0,
            'tasks' => 0,
        ]);
        $longHeadline = (string) __('app.performance_insight_headline_tier_low', [
            'interactions' => 0,
            'minutes' => 0,
            'tasks' => 0,
        ]);

        UserDailyPerformanceInsight::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'insight_date' => now()->toDateString(),
            'performance_ratio' => 10.0,
            'headline' => $longHeadline,
            'focus' => '',
            'message' => $longMessage,
            'context_snapshot' => [],
        ]);

        app()->setLocale('en');
        $service = app(UserDailyPerformanceInsightService::class);
        $insight = $service->ensureTodayRecord($user, $team, null, null, false, 'en');

        $focusWords = preg_split('/\s+/u', str_replace("\n", ' ', $insight->focus), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $this->assertCount(5, $focusWords);
        $this->assertCount(1, preg_split('/\s+/u', $insight->headline, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $this->assertGreaterThan(54, mb_strlen($insight->message));
        $this->assertNotSame($longMessage, $insight->message);
    }

    public function test_insight_service_force_regenerate_updates_snapshot(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        $service = app(UserDailyPerformanceInsightService::class);
        $first = $service->ensureTodayRecord($user, $team, 'Phase A');
        $second = $service->ensureTodayRecord($user, $team, 'Phase B', null, true);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Phase B', $second->context_snapshot['mentoring_phase_label'] ?? null);
    }

    public function test_split_headline_word_and_trailing_emoji_for_dashboard(): void
    {
        $this->assertSame(
            ['text' => 'Acción', 'emoji' => '🎯'],
            UserDailyPerformanceInsight::splitHeadlineWordAndTrailingEmoji('Acción🎯'),
        );
        $this->assertSame(
            ['text' => 'Glow', 'emoji' => '✨'],
            UserDailyPerformanceInsight::splitHeadlineWordAndTrailingEmoji('Glow✨'),
        );
        $this->assertSame(
            ['text' => 'Solo', 'emoji' => ''],
            UserDailyPerformanceInsight::splitHeadlineWordAndTrailingEmoji('Solo'),
        );
    }
}
