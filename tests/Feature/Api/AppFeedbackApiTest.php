<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class AppFeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'product' => 'ads',
            'answers' => [
                ['key' => 'satisfaction', 'choice' => 'satisfied'],
                ['key' => 'ease', 'choice' => 'easy'],
                ['key' => 'value', 'choice' => 'quite_a_bit'],
            ],
            'comment' => 'Me gustaría exportar las campañas a CSV.',
        ];
    }

    /**
     * @return array{0: User, 1: Team, 2: string}
     */
    private function userWithTeam(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $token = $user->createToken('feedback-test')->plainTextToken;

        return [$user->fresh(), $team->fresh(), $token];
    }

    public function test_authenticated_user_can_submit_feedback(): void
    {
        [$user, $team, $token] = $this->userWithTeam();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/feedback', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('app_feedback', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product' => 'ads',
            'comment' => 'Me gustaría exportar las campañas a CSV.',
        ]);
    }

    public function test_feedback_allows_empty_comment(): void
    {
        [, $team, $token] = $this->userWithTeam();
        $payload = $this->validPayload();
        unset($payload['comment']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/feedback', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('app_feedback', [
            'team_id' => $team->id,
            'product' => 'ads',
            'comment' => null,
        ]);
    }

    public function test_feedback_requires_all_answers(): void
    {
        [, , $token] = $this->userWithTeam();
        $payload = $this->validPayload();
        array_pop($payload['answers']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/feedback', $payload)
            ->assertUnprocessable();
    }

    public function test_feedback_rejects_unknown_choice(): void
    {
        [, , $token] = $this->userWithTeam();
        $payload = $this->validPayload();
        $payload['answers'][0]['choice'] = 'tokens';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/feedback', $payload)
            ->assertUnprocessable();
    }

    public function test_feedback_rejects_unknown_product(): void
    {
        [, , $token] = $this->userWithTeam();
        $payload = $this->validPayload();
        $payload['product'] = 'tokens';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/feedback', $payload)
            ->assertUnprocessable();
    }
}
