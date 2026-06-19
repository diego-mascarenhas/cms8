<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamResolveInboundWebhookTeamIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_destination_number_over_route_team_when_mapped(): void
    {
        $user = User::factory()->create();
        $routeTeam = Team::factory()->create(['user_id' => $user->id]);
        $numberTeam = Team::factory()->create(['user_id' => $user->id]);

        TeamSetting::query()->create([
            'team_id' => $numberTeam->id,
            'key' => 'whatsapp_from',
            'value' => '5491112223333',
            'type' => 'string',
            'group' => 'twilio',
            'is_encrypted' => false,
        ]);

        $this->assertSame(
            $numberTeam->id,
            Team::resolveInboundWebhookTeamId($routeTeam->id, '5491112223333'),
        );
    }

    public function test_falls_back_to_route_team_when_destination_number_is_unmapped(): void
    {
        $this->assertSame(42, Team::resolveInboundWebhookTeamId(42, '5491111111111'));
    }

    public function test_resolves_team_by_whatsapp_from_when_route_missing(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        TeamSetting::query()->create([
            'team_id' => $team->id,
            'key' => 'whatsapp_from',
            'value' => '5491112223333',
            'type' => 'string',
            'group' => 'twilio',
            'is_encrypted' => false,
        ]);

        $this->assertSame($team->id, Team::resolveInboundWebhookTeamId(null, '5491112223333'));
    }

    #[DataProvider('nullCasesProvider')]
    public function test_returns_null_when_no_match(?int $route, string $to): void
    {
        $this->assertNull(Team::resolveInboundWebhookTeamId($route, $to));
    }

    /**
     * @return array<string, array{0: int|null, 1: string}>
     */
    public static function nullCasesProvider(): array
    {
        return [
            'empty to' => [null, ''],
            'short to' => [null, '123'],
            'no route and wrong to' => [null, '5488776655443'],
        ];
    }
}
