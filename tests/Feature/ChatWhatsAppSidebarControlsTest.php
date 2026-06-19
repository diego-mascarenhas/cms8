<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatWhatsAppSidebarControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_avatar_binds_sidebar_without_waiting_for_dom_content_loaded(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertOk();

        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/id="chat-contacts-wa-avatar"[^>]*>/',
            $html,
        );
        preg_match('/id="chat-contacts-wa-avatar"[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('data-bs-toggle="sidebar"', $matches[0]);

        $appChatJs = file_get_contents(base_path('resources/assets/js/app-chat.js'));
        $this->assertNotFalse($appChatJs);
        $this->assertStringContainsString('bindChatWhatsAppSidebarControls', $appChatJs);
        $this->assertStringContainsString('applyChatSidebarSearch', $appChatJs);
        $this->assertStringContainsString('#chat-list-whatsapp li:not(.chat-contact-list-item-title)', $appChatJs);
        $this->assertStringContainsString('if (!listItem0)', $appChatJs);
        $this->assertLessThan(
            strpos($appChatJs, "document.addEventListener('DOMContentLoaded'"),
            strpos($appChatJs, 'bindChatWhatsAppSidebarControls'),
        );
    }
}
