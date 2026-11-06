<?php

namespace Tests\Feature;

use App\Enums\MeetingTranscriptStatus;
use App\Enums\TeamBillingProduct;
use App\Models\MeetingTranscript;
use App\Models\TeamBillingRate;
use App\Models\User;
use App\Services\TeamBillingUsageSummaryService;
use App\Services\TeamTranscriptionUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class TranscriptionUsageBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcribed_audio_is_billed_by_the_minute_on_the_usage_cycle(): void
    {
        $team = $this->team();

        $this->meeting($team->id, MeetingTranscriptStatus::Ready, 1800);
        $this->meeting($team->id, MeetingTranscriptStatus::Failed, 1800);

        $usage = app(TeamBillingUsageSummaryService::class)->currentMonth($team);

        $this->assertSame(1800, $usage['transcription_seconds']);
        $this->assertSame(111, $usage['transcription_billed_cents']);
        $this->assertSame(0, $usage['cost_cents']);
        $this->assertSame(0, $usage['markup_cents']);
        $this->assertSame(111, $usage['billed_cents']);
        $this->assertStringContainsString('30 min / 1,11 USD', $usage['formatted']['transcription']);

        $lines = app(TeamBillingUsageSummaryService::class)->billableLines($usage, $usage['period_label']);
        $line = collect($lines)->firstWhere('kind', 'transcription');

        $this->assertNotNull($line);
        $this->assertSame(111, $line['amount_cents']);
        $this->assertSame('30 min', $line['detail']);
    }

    public function test_a_later_rate_does_not_reprice_earlier_audio(): void
    {
        $team = $this->team();
        $changedAt = now()->subHour();

        $old = $this->meeting($team->id, MeetingTranscriptStatus::Ready, 3600);
        $old->forceFill(['created_at' => $changedAt->copy()->subMinutes(30)])->save();

        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::TranscriptionMinute, 0.05, $changedAt);

        $recent = $this->meeting($team->id, MeetingTranscriptStatus::Ready, 3600);
        $recent->forceFill(['created_at' => $changedAt->copy()->addMinutes(30)])->save();

        $stats = TeamTranscriptionUsageStatsService::forTeam($team, now()->subDay(), now());

        $this->assertSame(7200, $stats['audio_seconds']);
        $this->assertSame(522, $stats['our_amount_cents']);
        $this->assertEqualsWithDelta(0.05, $stats['our_rate'], 0.000001);
    }

    private function meeting(int $teamId, MeetingTranscriptStatus $status, int $seconds): MeetingTranscript
    {
        return MeetingTranscript::withoutGlobalScope('team')->create([
            'team_id' => $teamId,
            'title' => 'Reunión',
            'original_filename' => 'reunion.mp3',
            'audio_path' => 'meetings/reunion.mp3',
            'status' => $status,
            'turns' => [[
                'speaker' => 'speaker_0',
                'label' => 'Persona 1',
                'text' => 'Hola',
                'start_seconds' => 0,
                'end_seconds' => $seconds,
            ]],
        ]);
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
