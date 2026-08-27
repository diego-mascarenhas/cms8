<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\Assistant\AssistantInboundCategoryAssignmentService;
use App\Services\AssistantToolsService;
use App\Services\TeamSiteAssistantPromptService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantInboundCategoryAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private const BIENVENIDA = <<<'PROMPT'
# Flujo: bienvenida Repuestos Avenida

## Etiquetas (interno, no se lo digas al cliente)

En el primer mensaje que dé una pista, assign_contact_to_category con cada regla que matchee.

1. Fiat, Renault, Peugeot, Chevrolet o Nissan → ESQUINA, color azul.
2. Volkswagen, VW, Ford, Audi, Toyota, Kia o Mercedes → VW, color verde.
3. Compra, reclamo o envío de Mercado Libre → ML, color amarillo.
PROMPT;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(ContactStatusSeeder::class);
    }

    public function test_parses_arrow_rules_and_skips_generic_purchase_words(): void
    {
        $service = app(AssistantInboundCategoryAssignmentService::class);

        $rules = $service->parseAssignmentRules(self::BIENVENIDA);

        $this->assertCount(3, $rules);
        $this->assertSame('ESQUINA', $rules[0]['category']);
        $this->assertSame('azul', $rules[0]['color']);
        $this->assertContains('fiat', $rules[0]['keywords']);
        $this->assertContains('nissan', $rules[0]['keywords']);

        $this->assertSame('VW', $rules[1]['category']);
        $this->assertContains('vw', $rules[1]['keywords']);

        $this->assertSame('ML', $rules[2]['category']);
        $this->assertContains('mercado libre', $rules[2]['keywords']);
        $this->assertNotContains('compra', $rules[2]['keywords']);
    }

    public function test_matches_fiat_and_ignores_repuesto_or_plain_purchase(): void
    {
        $service = app(AssistantInboundCategoryAssignmentService::class);

        $this->assertSame(['ESQUINA'], array_column($service->matchingRules(self::BIENVENIDA, 'Fiat'), 'category'));
        $this->assertSame(['ESQUINA'], array_column($service->matchingRules(self::BIENVENIDA, 'un repuesto fiat'), 'category'));
        $this->assertSame([], $service->matchingRules(self::BIENVENIDA, 'repuesto'));
        $this->assertSame([], $service->matchingRules(self::BIENVENIDA, 'quiero comprar'));
        $this->assertSame(['ML'], array_column($service->matchingRules(self::BIENVENIDA, 'es un reclamo de Mercado Libre'), 'category'));
    }

    public function test_assigns_matched_category_when_the_model_skips_the_tool(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();
        $team = $user->currentTeam;
        $prompts = app(TeamSiteAssistantPromptService::class);
        $prompt = $prompts->create($team, 'Bienvenida', self::BIENVENIDA);
        $prompts->select($team, $prompts->routingKeyFor($prompt));

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'name' => 'Diego',
            'phone' => 34600000099,
        ]);

        $service = app(AssistantInboundCategoryAssignmentService::class);
        $applied = $service->tryApplyFromUserMessage(
            $user,
            (int) $team->id,
            'Fiat',
            $prompts->routingKeyFor($prompt),
            (int) $contact->id,
            '34600000099',
            [],
        );

        $this->assertNotNull($applied);
        $this->assertSame(['ESQUINA'], $applied['category_names']);
        $this->assertTrue($contact->categories()->where('categories.name', 'ESQUINA')->exists());
        $this->assertSame(['ESQUINA'], app(AssistantToolsService::class)->pullInternalCategoryAssignments());
    }

    public function test_skips_when_assign_tool_already_ran(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();
        $team = $user->currentTeam;
        $prompt = app(TeamSiteAssistantPromptService::class)->create($team, 'Bienvenida', self::BIENVENIDA);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'name' => 'Diego',
        ]);

        $tools = app(AssistantToolsService::class);
        $tools->clearRequestContext();
        $tools->setRequestContext($user->id, (int) $team->id, null, (int) $contact->id);
        $tools->execute('assign_contact_to_category', [
            'category_name' => 'ESQUINA',
            'color' => 'azul',
        ]);

        $applied = app(AssistantInboundCategoryAssignmentService::class)->tryApplyFromUserMessage(
            $user,
            (int) $team->id,
            'Fiat',
            'chat:'.$prompt->section_key,
            (int) $contact->id,
        );

        $this->assertNull($applied);
    }

    private function createAdminWithTeam(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    private function ensureContactsModule(): Module
    {
        return Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'Contacts module',
                'is_core' => true,
                'status' => 1,
            ],
        );
    }
}
