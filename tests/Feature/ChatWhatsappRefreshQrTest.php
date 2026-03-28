<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatWhatsappRefreshQrTest extends TestCase
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

    public function test_whatsapp_refresh_qr_returns_422_when_base_url_empty(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', '');

        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->postJson(route('chat.whatsapp-refresh-qr'), []);

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
        ]);
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_whatsapp_refresh_qr_returns_503_when_refresh_connection_fails(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://127.0.0.1:1');

        Http::fake(function ()
        {
            throw new ConnectionException('Connection refused');
        });

        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->postJson(route('chat.whatsapp-refresh-qr'), []);

        $response->assertStatus(503);
        $response->assertJson([
            'ok' => false,
        ]);
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_whatsapp_refresh_qr_returns_502_when_upstream_returns_error_status(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/refresh*' => Http::response('error', 500),
        ]);

        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->postJson(route('chat.whatsapp-refresh-qr'), []);

        $response->assertStatus(502);
        $response->assertJson([
            'ok' => false,
        ]);
        $this->assertStringContainsString('500', (string) $response->json('message'));
    }

    public function test_whatsapp_refresh_qr_returns_ok_when_refresh_succeeds(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/refresh*' => Http::response('ok', 200),
        ]);

        $user = $this->userWithTeam();

        $response = $this->actingAs($user)->postJson(route('chat.whatsapp-refresh-qr'), []);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
        ]);
        $this->assertNotEmpty($response->json('message'));
    }
}
