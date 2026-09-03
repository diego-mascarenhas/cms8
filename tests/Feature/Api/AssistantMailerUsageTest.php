<?php

namespace Tests\Feature\Api;

use App\Models\MailerUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantMailerUsageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function assistantUserWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team, $user->createToken('assistant-mailer-usage')->plainTextToken];
    }

    public function test_records_external_mailer_sends_for_the_current_team(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/mailer-usage', [
                'count' => 3,
                'source' => 'fanyion',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.count', 3);
        $response->assertJsonPath('data.source', 'fanyion');

        $this->assertSame(1, MailerUsageLog::query()->where('team_id', $team->id)->count());
        $this->assertSame(3, (int) MailerUsageLog::query()->where('team_id', $team->id)->sum('count'));
    }

    public function test_guest_cannot_record_mailer_usage(): void
    {
        $this->postJson('/api/assistant/mailer-usage', ['count' => 1])->assertUnauthorized();
    }
}
