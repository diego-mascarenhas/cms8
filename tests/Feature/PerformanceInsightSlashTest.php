<?php

namespace Tests\Feature;

use App\Livewire\AssistantChat;
use App\Models\AgentConversationMessage;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use App\Services\PerformanceInsightSlashDispatcher;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceInsightSlashTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_assistant_generates_insight_for_admin(): void
    {
        Mail::fake();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::firstOrCreate(
            ['key' => 'performance_insights'],
            ['name' => 'Performance insights', 'is_core' => false],
        );
        $team->enableModule('performance_insights');
        $team->refresh();

        config([
            'daily_performance_insight.send_email' => false,
            'daily_performance_insight.use_llm' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/generar-insight --force',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action_performed' => 'performance_insight_generate',
        ]);

        $this->assertTrue(
            UserDailyPerformanceInsight::query()
                ->where('user_id', $user->id)
                ->where('team_id', $team->id)
                ->whereDate('insight_date', now()->toDateString())
                ->exists(),
        );

        $this->assertTrue(
            AgentConversationMessage::query()
                ->where('user_id', $user->id)
                ->where('role', 'user')
                ->where('content', '/generar-insight --force')
                ->exists(),
        );
    }

    public function test_web_assistant_rejects_insight_slash_for_non_admin(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/generar-insight',
        ]);

        $response->assertStatus(403);
    }

    public function test_livewire_offcanvas_assistant_handles_performance_insight_slash(): void
    {
        Mail::fake();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::firstOrCreate(
            ['key' => 'performance_insights'],
            ['name' => 'Performance insights', 'is_core' => false],
        );
        $team->enableModule('performance_insights');
        $team->refresh();

        config([
            'daily_performance_insight.send_email' => false,
            'daily_performance_insight.use_llm' => false,
        ]);

        $component = Livewire::actingAs($user)
            ->test(AssistantChat::class, ['hideHeader' => true])
            ->set('input', '/performance-insight --force')
            ->call('sendMessage')
            ->assertSet('loading', false)
            ->assertCount('messages', 2);

        $messages = $component->get('messages');
        $this->assertStringContainsString('Insight', (string) ($messages[1]['content'] ?? ''));
    }

    public function test_dispatcher_parses_force_and_date(): void
    {
        $dispatcher = app(PerformanceInsightSlashDispatcher::class);

        $this->assertSame(
            ['force' => true, 'date' => '2026-05-10'],
            $dispatcher->parseBody('/generar-insight --force 2026-05-10'),
        );

        $this->assertSame(
            ['force' => false, 'date' => null],
            $dispatcher->parseBody('/insight-diario'),
        );

        $this->assertNull($dispatcher->parseBody('/generar-insight invalido'));
    }
}
