<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\Assistant\AssistantInboundContactCreationService;
use App\Services\AssistantToolsService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantInboundContactCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(ContactStatusSeeder::class);
    }

    public function test_parses_nuevo_contacto_message(): void
    {
        $service = app(AssistantInboundContactCreationService::class);

        $parsed = $service->parseContactCreationIntent(
            'Nuevo contacto: Adrián Mestas, uo302714@uniovi.es, 684664453, categoría estudiante',
        );

        $this->assertNotNull($parsed);
        $this->assertSame('Adrián Mestas', $parsed['name']);
        $this->assertSame('uo302714@uniovi.es', $parsed['email']);
        $this->assertSame('684664453', $parsed['phone']);
        $this->assertSame('estudiante', $parsed['category_name']);
    }

    public function test_parses_polite_contact_message_with_quoted_name(): void
    {
        $service = app(AssistantInboundContactCreationService::class);

        $parsed = $service->parseContactCreationIntent(
            'Podrás ingresar el contacto "Adrián Mestas" en la categoría Becarios, uo302714@uniovi.es, 684664453',
        );

        $this->assertNotNull($parsed);
        $this->assertSame('Adrián Mestas', $parsed['name']);
        $this->assertSame('uo302714@uniovi.es', $parsed['email']);
        $this->assertSame('684664453', $parsed['phone']);
        $this->assertSame('Becarios', $parsed['category_name']);
    }

    public function test_parses_contact_message_with_notes_and_birthday(): void
    {
        $service = app(AssistantInboundContactCreationService::class);

        $parsed = $service->parseContactCreationIntent(
            'Nuevo contacto: Adrián Mestas, adrian@example.com, 34684647725, nota: Cliente VIP, cumpleaños 1990-05-20',
        );

        $this->assertNotNull($parsed);
        $this->assertSame('Adrián Mestas', $parsed['name']);
        $this->assertSame('adrian@example.com', $parsed['email']);
        $this->assertSame('34684647725', $parsed['phone']);
        $this->assertSame('Cliente VIP', $parsed['notes']);
        $this->assertSame('1990-05-20', $parsed['birthday']);
    }

    public function test_parses_contact_message_with_birthday_in_day_month_year(): void
    {
        $service = app(AssistantInboundContactCreationService::class);

        $parsed = $service->parseContactCreationIntent(
            'Nuevo contacto: Adrián Mestas, adrian@example.com, cumpleaños 20/05/1990',
        );

        $this->assertNotNull($parsed);
        $this->assertSame('1990-05-20', $parsed['birthday']);
    }

    public function test_creates_contact_when_llm_skipped_tool(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();

        app(AssistantToolsService::class)->clearRequestContext();

        $applied = app(AssistantInboundContactCreationService::class)->tryApplyFromUserMessage(
            $user,
            (int) $user->currentTeam->id,
            'Nuevo contacto: Server Guard Person, guardperson@example.com, 611111111, categoría eventos',
            [],
        );

        $this->assertNotNull($applied);
        $this->assertSame(
            1,
            Contact::withoutGlobalScopes()
                ->where('team_id', $user->currentTeam->id)
                ->where('email', 'guardperson@example.com')
                ->count(),
        );
        $this->assertStringContainsString('Server Guard Person', $applied['whatsapp_reply']);
        $this->assertStringContainsString('(id '.$applied['contact_id'].')', $applied['whatsapp_reply']);
    }

    public function test_creates_contact_with_notes_and_birthday_when_llm_skipped_tool(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();

        app(AssistantToolsService::class)->clearRequestContext();

        $applied = app(AssistantInboundContactCreationService::class)->tryApplyFromUserMessage(
            $user,
            (int) $user->currentTeam->id,
            'Nuevo contacto: Adrián Mestas, adrian@example.com, 34684647725, nota: Cliente VIP, cumpleaños 1990-05-20',
            [],
        );

        $this->assertNotNull($applied);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $user->currentTeam->id)
            ->where('email', 'adrian@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('1990-05-20', $contact->birthday?->format('Y-m-d'));
        $this->assertSame('Cliente VIP', $contact->data->notes ?? null);
    }

    public function test_does_not_duplicate_when_create_contact_already_executed(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();

        $tools = app(AssistantToolsService::class);
        $tools->clearRequestContext();
        $tools->setRequestContext($user->id, (int) $user->currentTeam->id, null);
        $tools->execute('create_contact', [
            'name' => 'Already Created',
            'email' => 'already@example.com',
        ]);

        $applied = app(AssistantInboundContactCreationService::class)->tryApplyFromUserMessage(
            $user,
            (int) $user->currentTeam->id,
            'Nuevo contacto: Already Created, already@example.com, 622222222',
            [],
        );

        $this->assertNull($applied);
        $this->assertSame(
            1,
            Contact::withoutGlobalScopes()
                ->where('team_id', $user->currentTeam->id)
                ->where('email', 'already@example.com')
                ->count(),
        );
    }

    public function test_create_contact_sets_responsible_id_to_creator(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();

        app(AssistantToolsService::class)->clearRequestContext();
        app(AssistantToolsService::class)->setRequestContext($user->id, (int) $user->currentTeam->id, null);
        app(AssistantToolsService::class)->execute('create_contact', [
            'name' => 'Responsible Test',
            'email' => 'responsible@example.com',
        ]);

        $contact = Contact::withoutGlobalScopes()
            ->where('email', 'responsible@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame($user->id, (int) $contact->responsible_id);
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

    private function ensureContactsModule(): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            ['name' => 'Contacts', 'status' => true],
        );
    }
}
