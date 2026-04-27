<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatAiTogglePreferenceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_toggle_preference_does_not_change_team_auto_respond_setting(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $team->setSetting('assistant_auto_respond', '0');

        $response = $this->actingAs($user)->patchJson(route('chat.ai-toggle-preference'), [
            'on' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $team->refresh();
        $this->assertSame('0', (string) $team->getSetting('assistant_auto_respond', '0'));
        $this->assertFalse(
            filter_var($team->getSetting('chat_ai_assistance_blocked', false), FILTER_VALIDATE_BOOL),
            'Enabling AI should clear team opt-out (chat_ai_assistance_blocked).',
        );
    }

    public function test_ai_toggle_with_contact_id_stores_preference_on_contact_not_team_settings(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'data' => (object) ['notes' => 'keep-me'],
        ]);

        $team->setSetting('assistant_auto_respond', '0');

        $response = $this->actingAs($user)->patchJson(route('chat.ai-toggle-preference'), [
            'on' => false,
            'contact_id' => $contact->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $contact->refresh();
        $this->assertFalse($contact->data->chat_assistant_ai_enabled);
        $this->assertSame('keep-me', $contact->data->notes);

        $team->refresh();
        $this->assertSame('0', (string) $team->getSetting('assistant_auto_respond', '0'));
        $this->assertFalse(
            filter_var($team->getSetting('chat_ai_assistance_blocked', false), FILTER_VALIDATE_BOOL),
            'Contact-scoped toggle must not set team opt-out.',
        );
    }
}
