<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PromptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCreatedSeedsDefaultPromptsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deliberately does not dispatch TeamCreated: self-registration, the payment-link signup
     * and AuthController all create the team directly, so seeding must not depend on that event.
     */
    private function createTeam(): Team
    {
        $this->seed(ModuleSeeder::class);
        $user = User::factory()->create();

        return Team::factory()->create(['user_id' => $user->id]);
    }

    /**
     * @return list<string>
     */
    private function sectionKeysFor(int $teamId): array
    {
        return Prompt::withoutGlobalScope('team')
            ->forTeam($teamId)
            ->pluck('section_key')
            ->all();
    }

    public function test_a_new_team_gets_the_module_prompts_and_the_assistant_flows(): void
    {
        $team = $this->createTeam();

        $keys = $this->sectionKeysFor((int) $team->id);

        $this->assertContains('notes', $keys, 'Contact notes prompt missing for a fresh team.');
        $this->assertContains('email', $keys, 'Contact email prompt missing for a fresh team.');
        $this->assertContains('general', $keys, 'Router prompt missing for a fresh team.');
        $this->assertContains('collections', $keys, 'Collections prompt missing for a fresh team.');
        $this->assertContains('wordpress', $keys, 'WordPress assistant prompt missing for a fresh team.');

        $this->assertContains('assistant_catalogo', $keys, 'Catalog flow missing for a fresh team.');
        $this->assertContains('assistant_citas', $keys, 'Calendar flow missing for a fresh team.');
    }

    public function test_our_own_brand_sales_scripts_do_not_leak_into_client_teams(): void
    {
        $team = $this->createTeam();

        $keys = $this->sectionKeysFor((int) $team->id);

        $this->assertNotContains('wapify_me', $keys, 'The Wapify.Me script (with its Stripe link) must not be seeded into client teams.');
        $this->assertNotContains('landing', $keys, 'The Humano.app strategy framework must not be seeded into client teams.');
    }

    public function test_the_seeded_copy_is_the_optimized_one(): void
    {
        $team = $this->createTeam();

        $email = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'email')
            ->value('prompt_instruction');

        $this->assertStringContainsString('listos para enviar', (string) $email);
        $this->assertStringContainsString('No inventes', (string) $email);
    }

    public function test_reseeding_never_overwrites_copy_the_team_edited(): void
    {
        $team = $this->createTeam();

        $prompt = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'notes')
            ->first();
        $this->assertNotNull($prompt);
        $prompt->prompt_instruction = 'TEXTO DEL CLIENTE';
        $prompt->save();

        $added = PromptSeeder::seedForTeam($team);

        $this->assertSame(0, $added);
        $this->assertSame('TEXTO DEL CLIENTE', $prompt->fresh()->prompt_instruction);
    }
}
