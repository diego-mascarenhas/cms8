<?php

namespace Tests\Feature;

use App\Enums\TeamBillingProduct;
use App\Models\ProspectUsageLog;
use App\Models\TeamBillingRate;
use App\Models\User;
use App\Services\TeamBillingUsageSummaryService;
use App\Services\TeamProspectUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class ProspectUsageBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_prospect_credits_are_billed_on_the_usage_cycle(): void
    {
        $team = $this->team();
        $team->addProspectCreditsFromPurchase(10);

        $this->assertTrue($team->decrementProspectCredits(3));
        $this->assertFalse($team->fresh()->decrementProspectCredits(20));

        $this->assertDatabaseHas('prospect_usage_logs', [
            'team_id' => $team->id,
            'source' => 'import',
            'count' => 3,
        ]);
        $this->assertSame(1, ProspectUsageLog::query()->count());

        $usage = app(TeamBillingUsageSummaryService::class)->currentMonth($team);

        $this->assertSame(3, $usage['prospect_credits']);
        $this->assertSame(45, $usage['prospect_billed_cents']);
        $this->assertSame(45, $usage['billed_cents']);
        $this->assertStringContainsString('3 / 0,45 EUR', $usage['formatted']['prospect']);

        $lines = app(TeamBillingUsageSummaryService::class)->billableLines($usage, $usage['period_label']);
        $prospect = collect($lines)->firstWhere('kind', 'prospect');

        $this->assertNotNull($prospect);
        $this->assertSame(3, $prospect['quantity']);
        $this->assertSame(45, $prospect['amount_cents']);
        $this->assertSame('3 créditos', $prospect['detail']);
    }

    public function test_a_later_rate_does_not_reprice_earlier_credits(): void
    {
        $team = $this->team();
        $changedAt = now()->subHour();

        ProspectUsageLog::factory()->create([
            'team_id' => $team->id,
            'count' => 2,
            'consumed_at' => $changedAt->copy()->subHour(),
        ]);
        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::ProspectCredit, 0.5, $changedAt);
        $team->addProspectCreditsFromPurchase(5);
        $this->assertTrue($team->decrementProspectCredits(1));

        $stats = TeamProspectUsageStatsService::forTeam($team, now()->subDay(), now());

        $this->assertSame(3, $stats['credits_used']);
        $this->assertSame(80, $stats['our_amount_cents']);
        $this->assertEqualsWithDelta(0.5, $stats['our_rate'], 0.000001);
        $this->assertSame('0.15', TeamBillingRate::formattedAmountOn((int) $team->id, TeamBillingProduct::ProspectCredit, $changedAt->copy()->subMinute()));
    }

    private function team(): \App\Models\Team
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();

        return $user->ownedTeams()->first();
    }
}
