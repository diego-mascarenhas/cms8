<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailerUsageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'mailer'],
            [
                'name' => 'Mailer',
                'icon' => 'mail',
                'description' => 'Mailer',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('mailer');

        return [$user, $team->fresh(), $user->createToken('idoneo-mailer-usage')->plainTextToken];
    }

    public function test_returns_subscriber_and_email_usage(): void
    {
        [, , $token] = $this->adminWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/usage');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subscribers_used', 0)
            ->assertJsonStructure([
                'data' => [
                    'subscribers_used',
                    'subscribers_limit',
                    'emails_used',
                    'emails_included',
                    'plan_name',
                ],
            ]);

        $this->assertGreaterThan(0, (int) $response->json('data.subscribers_limit'));
    }

    public function test_guest_cannot_see_mailer_usage(): void
    {
        $this->getJson('/api/mailer/usage')->assertUnauthorized();
    }
}
