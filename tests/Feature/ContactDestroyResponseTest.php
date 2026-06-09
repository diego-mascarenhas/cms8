<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactDestroyResponseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'CRM contacts',
                'status' => 1,
            ],
        );

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');
        $team->enableModule('contacts');
    }

    public function test_destroy_redirects_to_contact_list_for_form_submission(): void
    {
        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('contact.destroy', $contact->id));

        $response->assertRedirect(route('contact-list'));
        $response->assertSessionHas('success', __('messages.success.deleted'));
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_destroy_returns_json_for_ajax_request(): void
    {
        $contact = Contact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('contact.destroy', $contact->id));

        $response->assertOk();
        $response->assertJson(['success' => __('messages.success.deleted')]);
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }
}
