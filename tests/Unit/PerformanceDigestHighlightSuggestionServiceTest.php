<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use App\Services\DailyTeamDigestMetricsCollector;
use App\Services\PerformanceDigestHighlightSuggestionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceDigestHighlightSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrich_items_returns_suggestion_and_action_url(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $items = app(DailyTeamDigestMetricsCollector::class)->buildHighlightItems([
            'whatsapp' => ['unread_inbound' => 2, 'inbound_24h' => 0, 'inbound_7d' => 2],
            'user_activity' => ['interactions_count' => 1, 'call_minutes' => 0, 'tasks_done' => 0],
        ], $team);

        $enriched = app(PerformanceDigestHighlightSuggestionService::class)->enrichItems($items, $team);

        $this->assertCount(1, $enriched);
        $this->assertSame('whatsapp_unread', $enriched[0]['key']);
        $this->assertStringContainsString('2', $enriched[0]['label']);
        $this->assertStringNotContainsString('(s)', $enriched[0]['label']);
        app()->setLocale('es');
        $itemsEs = app(DailyTeamDigestMetricsCollector::class)->buildHighlightItems([
            'whatsapp' => ['unread_inbound' => 2, 'inbound_24h' => 0, 'inbound_7d' => 2],
            'user_activity' => ['interactions_count' => 1, 'call_minutes' => 0, 'tasks_done' => 0],
        ], $team);
        $this->assertStringContainsString('mensajes', $itemsEs[0]['label']);
        $this->assertNotSame('', $enriched[0]['suggestion']);
        $this->assertNotNull($enriched[0]['action_url']);
        $this->assertNull($enriched[0]['schedule_action'] ?? null);
        $this->assertArrayHasKey('detail_mode', $enriched[0]);
        $this->assertArrayHasKey('messages', $enriched[0]);
        $this->assertSame('single', $enriched[0]['detail_mode']);
    }

    public function test_for_insight_rebuilds_items_from_snapshot_metrics(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $insight = UserDailyPerformanceInsight::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'insight_date' => Carbon::today(),
            'performance_ratio' => 10.0,
            'headline' => 'Focus',
            'focus' => 'Reply inbox tasks calls',
            'message' => 'Test message body.',
            'context_snapshot' => [
                'highlights' => [__('app.performance_digest_highlight_email_unread', ['count' => 3])],
                'email' => ['unread' => 3],
                'user_activity' => ['interactions_count' => 0, 'call_minutes' => 0, 'tasks_done' => 0],
            ],
        ]);

        $enriched = app(PerformanceDigestHighlightSuggestionService::class)->forInsight($insight, $team);

        $this->assertCount(1, $enriched);
        $this->assertSame('email_unread', $enriched[0]['key']);
        $this->assertSame(3, $enriched[0]['count']);
    }
}
