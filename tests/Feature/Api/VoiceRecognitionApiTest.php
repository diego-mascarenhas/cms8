<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VoiceRecognitionUsageLog;
use App\Services\TeamBillingUsageSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class VoiceRecognitionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_token_lists_enrolled_voices_and_records_usage(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create(['name' => 'Ada Lovelace']);
        $team = $user->ownedTeams()->first();
        $user->forceFill([
            'current_team_id' => $team->id,
            'voice_speaker_id' => 'spk_ada',
        ])->save();
        $outsider = User::factory()->withPersonalTeam()->create(['name' => 'Grace Hopper']);
        $outsider->forceFill(['voice_speaker_id' => 'spk_grace'])->save();
        $token = $user->createToken('assistant')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/voices')
            ->assertOk()
            ->assertJsonPath('data.0.user_id', $user->id)
            ->assertJsonPath('data.0.speaker_id', 'spk_ada')
            ->assertJsonMissing(['speaker_id' => 'spk_grace']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/voices/usage', [
                'seconds' => 90,
                'source' => 'assistant',
                'user_id' => $user->id,
                'external_ref' => 'call-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.seconds', 90)
            ->assertJsonPath('data.source', 'assistant');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/voices/usage', [
                'seconds' => 30,
                'source' => 'assistant',
                'external_ref' => 'call-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.seconds', 30);

        $this->assertSame(1, VoiceRecognitionUsageLog::query()->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/voices/usage')
            ->assertOk()
            ->assertJsonPath('data.seconds', 30);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/voices/usage', [
                'seconds' => 10,
                'user_id' => $outsider->id,
            ])
            ->assertStatus(422);
    }

    public function test_a_team_token_records_voice_usage(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $token = $team->createApiToken('Assistant')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/team/voices/usage', [
                'seconds' => 120,
                'source' => 'meet',
            ])
            ->assertCreated()
            ->assertJsonPath('data.seconds', 120);

        $usage = app(TeamBillingUsageSummaryService::class)->currentMonth($team);

        $this->assertSame(120, $usage['recognition_seconds']);
        $this->assertSame(7, $usage['recognition_billed_cents']);
        $this->assertSame(7, $usage['billed_cents']);
    }

    public function test_guests_cannot_read_voices(): void
    {
        $this->getJson('/api/voices')->assertUnauthorized();
        $this->getJson('/api/team/voices')->assertUnauthorized();
    }
}
