<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSendActionsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_chat_shows_split_send_actions_and_header_assistant_toggle(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'phone' => '722372858',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('chat.index', ['phone' => '722372858']));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('chat-send-primary-btn waves-effect waves-light" disabled', $html);
        $this->assertStringContainsString('chat-send-dropdown-toggle', $html);
        $this->assertStringContainsString('updateChatSendButtonsState', $html);
        $this->assertStringContainsString('chatSendActionsCanSubmit', $html);
        $this->assertStringContainsString('name="send_intent" value="suggest"', $html);
        $this->assertStringContainsString('id="chatScheduleModal"', $html);
        $this->assertStringContainsString('id="use-ai-toggle"', $html);
        $this->assertStringContainsString('useAiToggle && useAiToggle.checked', $html);
        $this->assertStringContainsString('dropdown-toggle-split', $html);
        $this->assertStringContainsString('data-bs-target="#chatScheduleModal"', $html);
        $this->assertStringContainsString('chatJsLocale', $html);
        $this->assertStringContainsString('chatScheduleDefaultDate', $html);
        $this->assertStringContainsString('applyChatScheduleDefaultDate', $html);
        $this->assertStringNotContainsString("toLocaleString('es_ES'", $html);
        $this->assertStringContainsString('ti-eye', $html);
        $this->assertStringNotContainsString('send-msg-text-ai', $html);
    }
}
