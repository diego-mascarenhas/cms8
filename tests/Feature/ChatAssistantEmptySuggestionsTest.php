<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAssistantEmptySuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_view_shows_module_suggestion_buttons_when_history_empty(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->get(route('chat.index', ['view' => 'assistant']));

        $response->assertOk();
        $response->assertSee('assistant-empty-suggestions', false);
        $response->assertSee('assistant-suggestion-example', false);
        $response->assertSee('data-prompt=', false);
        $response->assertSee('id="assistant-suggestions-source"', false);
    }
}
