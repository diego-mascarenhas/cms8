<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppQrImageAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_after_session_expired_on_whatsapp_qr_image_redirects_to_home_not_png(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->get(route('chat.whatsapp-qr-image', ['t' => time()]))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
        $response->assertRedirect('/');

        $target = $response->headers->get('Location');
        $this->assertIsString($target);
        $this->assertStringNotContainsString('whatsapp-qr-image', $target);
    }
}
