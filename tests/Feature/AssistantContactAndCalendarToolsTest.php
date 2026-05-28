<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use App\Support\CalendarEventDateTimeParser;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantContactAndCalendarToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(ContactStatusSeeder::class);
    }

    public function test_search_contacts_returns_matching_contact_with_id(): void
    {
        $user = $this->createAdminWithTeam();

        Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'name' => 'Francisco',
            'surname' => 'Caballero',
            'email' => 'francisco@example.com',
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('search_contacts', ['query' => 'Francisco Caballero']);

        $this->assertStringContainsString('Found 1 contact', $out);
        $this->assertStringContainsString('Francisco Caballero', $out);
        $this->assertStringContainsString('francisco@example.com', $out);
        $this->assertMatchesRegularExpression('/id \d+:/', $out);
    }

    public function test_get_contact_detail_returns_full_contact_data(): void
    {
        $user = $this->createAdminWithTeam();

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Leticia',
            'surname' => 'Silvano Martínez',
            'email' => 'leticia@example.com',
            'phone' => 34662123626,
            'data' => (object) ['notes' => 'Existing CRM note'],
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('get_contact_detail', ['contact_id' => $contact->id]);

        $this->assertStringContainsString('Contact detail: Leticia Silvano Martínez', $out);
        $this->assertStringContainsString('Email: leticia@example.com', $out);
        $this->assertStringContainsString('Phone: 34662123626', $out);
        $this->assertStringContainsString('Notes: Existing CRM note', $out);
        $this->assertStringContainsString('Profile URL:', $out);
    }

    public function test_create_contact_reuses_existing_by_full_name(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();

        Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'name' => 'Francisco',
            'surname' => 'Caballero',
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('create_contact', [
            'name' => 'Francisco Caballero',
        ]);

        $this->assertStringContainsString('already exists', $out);
        $this->assertEquals(1, Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->count());
    }

    public function test_create_contact_reuses_existing_by_email(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();

        $existing = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'email' => 'ana@example.com',
            'name' => 'Ana',
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('create_contact', [
            'name' => 'Ana Duplicate',
            'email' => 'ana@example.com',
        ]);

        $this->assertStringContainsString('already exists', $out);
        $this->assertStringContainsString((string) $existing->id, $out);
        $this->assertEquals(1, Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->count());
    }

    public function test_create_contact_stores_notes_and_birthday(): void
    {
        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();

        $service = $this->assistantTools($user);
        $out = $service->execute('create_contact', [
            'name' => 'Luis',
            'email' => 'luis@example.com',
            'notes' => 'Prefers morning calls',
            'birthday' => '1990-05-20',
        ]);

        $this->assertStringContainsString('Contact created', $out);

        $contact = Contact::withoutGlobalScopes()->where('email', 'luis@example.com')->firstOrFail();
        $this->assertSame('1990-05-20', $contact->birthday?->format('Y-m-d'));
        $this->assertSame('Prefers morning calls', $contact->data->notes ?? null);
    }

    public function test_update_contact_sets_notes_and_birthday(): void
    {
        $user = $this->createAdminWithTeam();

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('update_contact', [
            'contact_id' => $contact->id,
            'notes' => 'VIP client',
            'birthday' => '1985-12-01',
        ]);

        $this->assertStringContainsString('updated', $out);

        $contact->refresh();
        $this->assertSame('1985-12-01', $contact->birthday?->format('Y-m-d'));
        $this->assertSame('VIP client', $contact->data->notes ?? null);
    }

    public function test_update_contact_splits_full_name_into_name_and_surname(): void
    {
        $user = $this->createAdminWithTeam();

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Temp',
            'surname' => 'Value',
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('update_contact', [
            'contact_id' => $contact->id,
            'name' => 'Adrián Mestas',
        ]);

        $this->assertStringContainsString('updated', $out);

        $contact->refresh();
        $this->assertSame('Adrián', $contact->name);
        $this->assertSame('Mestas', $contact->surname);
    }

    public function test_create_calendar_event_rejects_overlapping_slot(): void
    {
        config(['calendar.wall_clock_timezone' => 'Europe/Madrid']);

        $user = $this->createAdminWithTeam();

        CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Busy block',
            'start' => CalendarEventDateTimeParser::parseForStorage('2026-05-17 10:00:00'),
            'end' => CalendarEventDateTimeParser::parseForStorage('2026-05-17 11:00:00'),
            'all_day' => false,
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('create_calendar_event', [
            'title' => 'New meeting',
            'start' => '2026-05-17 10:30:00',
            'end' => '2026-05-17 11:30:00',
        ]);

        $this->assertStringContainsString('Cannot create the event', $out);
        $this->assertStringContainsString('Busy block', $out);
        $this->assertEquals(1, CalendarEvent::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->count());
    }

    public function test_create_calendar_event_stores_naive_wall_clock_time(): void
    {
        config(['calendar.wall_clock_timezone' => 'Europe/Madrid']);

        $user = $this->createAdminWithTeam();
        $service = $this->assistantTools($user);

        $out = $service->execute('create_calendar_event', [
            'title' => 'Improvements call',
            'start' => '2026-06-10 14:00:00',
            'end' => '2026-06-10 15:00:00',
        ]);

        $this->assertStringContainsString('Calendar event created', $out);

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $user->currentTeam->id)
            ->firstOrFail();

        $this->assertSame('12:00:00', $event->start->utc()->format('H:i:s'));
        $this->assertSame('13:00:00', $event->end->utc()->format('H:i:s'));
    }

    public function test_create_calendar_event_links_guest_contacts(): void
    {
        $user = $this->createAdminWithTeam();

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'name' => 'Francisco',
            'surname' => 'Caballero',
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('create_calendar_event', [
            'title' => 'Improvements call',
            'start' => '2026-06-10 14:00:00',
            'end' => '2026-06-10 15:00:00',
            'guest_contact_ids' => [$contact->id],
        ]);

        $this->assertStringContainsString('Guests linked', $out);
        $this->assertStringContainsString('Francisco Caballero', $out);

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $user->currentTeam->id)
            ->firstOrFail();

        $this->assertTrue($event->guests()->where('contacts.id', $contact->id)->exists());
        $this->assertNull($event->notes);
    }

    public function test_create_calendar_event_auto_links_contact_created_in_same_request(): void
    {
        config(['calendar.wall_clock_timezone' => 'Europe/Madrid']);

        $user = $this->createAdminWithTeam();
        $this->ensureContactsModule();

        $service = $this->assistantTools($user);

        $service->execute('create_contact', [
            'name' => 'Francisco Caballero',
            'email' => 'francisco@example.com',
        ]);

        $out = $service->execute('create_calendar_event', [
            'title' => 'Reunión con Francisco Caballero',
            'start' => '2026-05-20 10:00:00',
            'end' => '2026-05-20 11:00:00',
        ]);

        $this->assertStringContainsString('Guests linked', $out);
        $this->assertStringContainsString('Francisco Caballero', $out);

        $contact = Contact::withoutGlobalScopes()
            ->where('email', 'francisco@example.com')
            ->firstOrFail();

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $user->currentTeam->id)
            ->firstOrFail();

        $this->assertTrue($event->guests()->where('contacts.id', $contact->id)->exists());
    }

    public function test_create_calendar_event_resolves_guest_from_title_without_prior_create(): void
    {
        $user = $this->createAdminWithTeam();

        Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'name' => 'Francisco',
            'surname' => 'Caballero',
        ]);

        $service = $this->assistantTools($user);
        $out = $service->execute('create_calendar_event', [
            'title' => 'Reunión con Francisco Caballero',
            'start' => '2026-05-20 10:00:00',
            'end' => '2026-05-20 11:00:00',
        ]);

        $this->assertStringContainsString('Guests linked', $out);

        $event = CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $user->currentTeam->id)
            ->firstOrFail();

        $this->assertEquals(1, $event->guests()->count());
    }

    public function test_create_contact_uses_existing_category_case_insensitively(): void
    {
        $user = $this->createAdminWithTeam();
        $module = $this->ensureContactsModule();

        Category::withoutGlobalScopes()->create([
            'name' => 'Partners',
            'module_id' => $module->id,
            'team_id' => $user->currentTeam->id,
            'status' => true,
        ]);

        $service = $this->assistantTools($user);
        $service->execute('create_contact', [
            'name' => 'One',
            'email' => 'one@example.com',
            'category_name' => 'partners',
        ]);

        $this->assertEquals(1, Category::withoutGlobalScopes()
            ->where('team_id', $user->currentTeam->id)
            ->where('module_id', $module->id)
            ->count());
    }

    private function assistantTools(User $user): AssistantToolsService
    {
        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $user->currentTeam->id, null);

        return $service;
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
