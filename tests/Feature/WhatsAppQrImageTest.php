<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppQrImageTest extends TestCase
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

    public function test_whatsapp_qr_image_returns_204_when_node_has_no_qr_yet(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/qr.png*' => Http::response('', 404),
        ]);

        $user = $this->userWithTeam();

        $this->actingAs($user)
            ->get(route('chat.whatsapp-qr-image'))
            ->assertNoContent();
    }

    public function test_whatsapp_qr_image_returns_png_when_node_serves_qr(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        $body = str_repeat($png, 40);

        Http::fake([
            'wa.test/qr.png*' => Http::response($body, 200, ['Content-Type' => 'image/png']),
        ]);

        $user = $this->userWithTeam();

        $this->actingAs($user)
            ->get(route('chat.whatsapp-qr-image'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
