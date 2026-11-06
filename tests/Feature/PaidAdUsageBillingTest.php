<?php

namespace Tests\Feature;

use App\Enums\PaidAdUsageKind;
use App\Enums\TeamBillingProduct;
use App\Models\PaidAdUsageLog;
use App\Models\TeamBillingRate;
use App\Models\User;
use App\Services\TeamBillingUsageSummaryService;
use App\Services\TeamPaidAdUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class PaidAdUsageBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_voice_characters_and_finished_reels_are_billed_on_the_usage_cycle(): void
    {
        $team = $this->team();

        PaidAdUsageLog::record((int) $team->id, PaidAdUsageKind::Voice, 1000);
        PaidAdUsageLog::record((int) $team->id, PaidAdUsageKind::Video, 1);

        $usage = app(TeamBillingUsageSummaryService::class)->currentMonth($team);

        $this->assertSame(1000, $usage['voice_characters']);
        $this->assertSame(80, $usage['voice_billed_cents']);
        $this->assertSame(1, $usage['videos']);
        $this->assertSame(189, $usage['video_billed_cents']);
        $this->assertSame(0, $usage['cost_cents']);
        $this->assertSame(269, $usage['billed_cents']);
        $this->assertSame('1.000 / 0,80 USD', $usage['formatted']['voice']);
        $this->assertSame('1 / 1,89 USD', $usage['formatted']['video']);

        $lines = app(TeamBillingUsageSummaryService::class)->billableLines($usage, $usage['period_label']);

        $this->assertSame(80, collect($lines)->firstWhere('kind', 'voice')['amount_cents']);
        $this->assertSame('1.000 caracteres', collect($lines)->firstWhere('kind', 'voice')['detail']);
        $this->assertSame(189, collect($lines)->firstWhere('kind', 'video')['amount_cents']);
        $this->assertSame('1 vídeo', collect($lines)->firstWhere('kind', 'video')['detail']);
    }

    public function test_a_later_rate_does_not_reprice_earlier_voice_or_video(): void
    {
        $team = $this->team();
        $changedAt = now()->subHour();

        $voice = PaidAdUsageLog::query()->create([
            'team_id' => $team->id,
            'kind' => PaidAdUsageKind::Voice,
            'quantity' => 1000,
        ]);
        $voice->forceFill(['created_at' => $changedAt->copy()->subMinutes(30)])->save();
        $video = PaidAdUsageLog::query()->create([
            'team_id' => $team->id,
            'kind' => PaidAdUsageKind::Video,
            'quantity' => 1,
        ]);
        $video->forceFill(['created_at' => $changedAt->copy()->subMinutes(30)])->save();

        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::ElevenlabsVoiceThousand, 1, $changedAt);
        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::PaidAdVideo, 2, $changedAt);

        PaidAdUsageLog::record((int) $team->id, PaidAdUsageKind::Voice, 1000);
        PaidAdUsageLog::record((int) $team->id, PaidAdUsageKind::Video, 1);

        $stats = TeamPaidAdUsageStatsService::forTeam($team, now()->subDay(), now());

        $this->assertSame(2000, $stats['characters']);
        $this->assertSame(180, $stats['voice_amount_cents']);
        $this->assertSame(2, $stats['videos']);
        $this->assertSame(389, $stats['video_amount_cents']);
        $this->assertEqualsWithDelta(1.0, $stats['voice_rate'], 0.000001);
        $this->assertEqualsWithDelta(2.0, $stats['video_rate'], 0.000001);
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
