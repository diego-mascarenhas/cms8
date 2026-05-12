<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use App\Services\UserDailyPerformanceInsightService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseDepartmentSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SourceSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        return $user->refresh();
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
    }

    public function test_dashboard_persists_daily_insight_and_shows_message(): void
    {
        $this->seedInsightDependencies();
        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
            'source_id' => 1,
        ]);

        ContactInteraction::query()->create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'relatable_type' => null,
            'relatable_id' => null,
            'type' => 'note',
            'subject' => 'Test',
            'body' => 'Body',
            'occurred_at' => now(),
        ]);

        $this->actingAs($user);
        app()->setLocale('en');

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $this->assertDatabaseHas('user_daily_performance_insights', [
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $insight = UserDailyPerformanceInsight::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($insight);
        $headlineParts = UserDailyPerformanceInsight::splitHeadlineWordAndTrailingEmoji($insight->headline);
        $response->assertSee($headlineParts['text'], false);
        if ($headlineParts['emoji'] !== '')
        {
            $response->assertSee($headlineParts['emoji'], false);
        }
        $focusWords = preg_split('/\s+/u', str_replace("\n", ' ', $insight->focus), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $this->assertNotEmpty($focusWords);
        $response->assertSee($focusWords[0], false);
        $response->assertSee(mb_substr((string) $insight->message, 0, 40), false);
        $response->assertSee(__('app.dashboard_open_assistant'), false);
        $response->assertSee('view=assistant', false);
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
        $this->assertSame(trans('app.performance_insight_emergency_focus_low', [], 'en'), $insight->focus);
        $this->assertSame(trans('app.performance_insight_emergency_headline_low', [], 'en'), $insight->headline);
        $label = trans('app.performance_insight_emergency_mentoring_default', [], 'en');
        $this->assertSame(trans('app.performance_insight_emergency_message_low_idle', [
            'interactions' => 0,
            'minutes' => 0,
            'tasks' => 0,
            'mentoring_label' => $label,
        ], 'en'), $insight->message);
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
