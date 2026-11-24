<?php

namespace Tests\Feature;

use App\Enums\TeamBillingProduct;
use App\Models\TeamBillingRate;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\TokenBillingRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillBilledUsageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_uses_the_multiplier_from_each_created_at(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $when = now()->subDay();
        $multiplierThen = TokenBillingRateService::clientTokenMultiplier($team, $when);

        $log = TokenUsageLog::withoutEvents(function () use ($team)
        {
            return TokenUsageLog::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'module_id' => null,
                'service' => 'PromptController',
                'json_size' => 20,
                'toon_size' => 10,
                'json_tokens' => 140,
                'toon_tokens' => 100,
                'savings_percentage' => 29,
                'used_toon' => true,
                'billed_usage' => null,
            ]);
        });
        $log->forceFill(['created_at' => $when, 'updated_at' => $when])->saveQuietly();

        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::TokensMultiplier, $multiplierThen + 7);

        $this->artisan('billing:backfill-billed-usage', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertNull($log->fresh()->billed_usage);

        $this->artisan('billing:backfill-billed-usage')
            ->assertSuccessful();

        $log->refresh();

        $this->assertSame((int) round(100 * $multiplierThen), (int) $log->billed_usage['prompt_tokens']);
        $this->assertSame(0, (int) $log->billed_usage['completion_tokens']);
        $this->assertSame((int) round(40 * $multiplierThen), (int) $log->billed_usage['tokens_saved']);

        $this->artisan('billing:backfill-billed-usage')
            ->expectsOutputToContain('Logs updated: 0')
            ->assertSuccessful();
    }
}
