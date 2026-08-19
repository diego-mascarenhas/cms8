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
        [$user, $team] = $this->assistantUser();
        $this->enableTeamModules($team, ['contacts', 'tasks']);

        $response = $this->actingAs($user)->get(route('chat.index', ['view' => 'assistant']));

        $response->assertOk();
        $response->assertSee('id="assistant-suggestions-source"', false);
        $response->assertSee('data-prompt=', false);
        $response->assertSee('>Crear contacto<', false);
        $response->assertSee('>Crear tarea<', false);
    }

    public function test_assistant_view_hides_suggestions_for_inactive_team_modules(): void
    {
        [$user, $team] = $this->assistantUser();
        $this->enableTeamModules($team, ['contacts', 'tickets', 'campaigns']);

        foreach (['tickets', 'campaigns'] as $moduleKey)
        {
            $team->disableModule($moduleKey);
        }

        $team->unsetRelation('modules');

        $response = $this->actingAs($user)->get(route('chat.index', ['view' => 'assistant']));

        $response->assertOk();
        $response->assertSee('>Crear contacto<', false);
        $response->assertDontSee('>Abrir ticket<', false);
        $response->assertDontSee('>Listar campañas<', false);
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function assistantUser(): array
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user->refresh(), $team];
    }
}
