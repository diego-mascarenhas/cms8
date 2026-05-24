<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatWhatsappWarmupQrTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTeam(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_warmup_skips_when_gateway_already_waiting_for_qr(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'waiting_qr', 'number' => null], 200),
        ]);

        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->postJson(route('chat.whatsapp-warmup-qr'));

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'skipped' => 'waiting_qr',
        ]);
        Http::assertSentCount(1);
    }

    public function test_warmup_calls_node_warmup_when_disconnected(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'disconnected', 'number' => null], 200),
            'wa.test/warmup*' => Http::response(['ok' => true, 'status' => 'disconnected'], 200),
        ]);

        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->postJson(route('chat.whatsapp-warmup-qr'));

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_warmup_returns_503_when_service_unreachable(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'disconnected', 'number' => null], 200),
            'wa.test/warmup*' => function ()
            {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->postJson(route('chat.whatsapp-warmup-qr'));

        $response->assertStatus(503);
        $response->assertJson(['ok' => false]);
    }
}
