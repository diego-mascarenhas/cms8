<?php

namespace Tests\Feature;

use App\Enums\ContactInteractionType;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Module;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactTicketsTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ContactStatusSeeder::class, CountrySeeder::class, LanguageSeeder::class]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );
    }

    public function test_contact_profile_lists_that_clients_tickets(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $client = User::factory()->create([
            'email' => 'cliente@example.com',
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'user_id' => $client->id,
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        Ticket::factory()->create([
            'team_id' => $team->id,
            'user_id' => $client->id,
            'subject' => 'Prueba desde mobile',
            'status' => 'in_progress',
            'priority' => 'low',
        ]);

        Ticket::factory()->create([
            'team_id' => $team->id,
            'subject' => 'Ticket de otra persona',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->get(route('contact.show', $contact->id))
            ->assertOk()
            ->assertSee('rounded-circle', false)
            ->assertSee('id="tickets-tab"', false)
            ->assertDontSee('id="emotional-balance-tab"', false)
            ->assertSee('Histórico emocional', false)
            ->assertSee('Aún no se registró ningún estado emocional.', false)
            ->assertSee('Prueba desde mobile')
            ->assertDontSee('Ticket de otra persona');
    }

    public function test_activity_form_styles_the_type_select_and_shows_a_calendar_button(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('contact.show', $contact->id))
            ->assertOk()
            ->assertSee('id="interaction-type"', false)
            ->assertSee("jQuery('#interaction-type')", false)
            ->assertSee('minimumResultsForSearch: Infinity', false)
            ->assertSee('id="occurred-at-calendar"', false)
            ->assertSee('ti ti-calendar', false)
            ->assertSee('instance.open()', false);
    }

    public function test_general_activity_lists_only_the_type_and_the_activity_tab_collapses_the_chat(): void
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        $interaction = ContactInteraction::factory()->create([
            'contact_id' => $contact->id,
            'user_id' => $admin->id,
            'type' => ContactInteractionType::WhatsApp,
            'subject' => 'Programa de afiliados',
            'body' => "[09/10/2026, 13:14:46] Diego: Hola Ale, cómo andás?\n[09/10/2026, 13:52:18] María Alejandra Arellano: Hola",
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('contact.show', $contact->id));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Programa de afiliados'));
        $this->assertSame(1, substr_count($html, 'Hola Ale, cómo andás?'));
        $this->assertStringNotContainsString('[09/10/2026, 13:14:46] Diego:', $html);
        $response->assertSee('id="interaction-body-'.$interaction->id.'"', false);
        $response->assertSee('class="collapse"', false);
        $response->assertSee('María Alejandra Arellano', false);
        $activityAt = strpos($html, 'ti-history');
        $this->assertNotFalse($activityAt);
        $this->assertFalse(strpos($html, 'Coste de adquisición', $activityAt));
        $source = file_get_contents(resource_path('views/contact/partials/general.blade.php'));
        $this->assertLessThan(strpos($source, 'ti-history'), strpos($source, 'Coste de adquisición'));
    }

    public function test_contact_form_styles_the_enterprise_selects(): void
    {
        $this->seed(EnterpriseTypeSeeder::class);
        DB::table('enterprise_statuses')->insert([
            ['id' => 1, 'name' => 'Inactivo', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-danger'],
            ['id' => 2, 'name' => 'Activo', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-success'],
        ]);

        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $team->enableModule('contacts');
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($admin)
            ->get(route('contact.create'))
            ->assertOk()
            ->assertSee('id="enterprise_enterprise_id"', false)
            ->assertSee('id="enterprise_department_id"', false)
            ->assertSee('id="enterprise_status_id"', false)
            ->assertSee("selector: '#enterprise_enterprise_id'", false)
            ->assertSee("selector: '#enterprise_department_id'", false)
            ->assertSee("selector: '#enterprise_status_id'", false)
            ->assertSee('dropdownParent: jQuery(document.body)', false)
            ->assertSee('shown.bs-stepper', false)
            ->assertSee('minimumResultsForSearch: Infinity', false);
    }
}
