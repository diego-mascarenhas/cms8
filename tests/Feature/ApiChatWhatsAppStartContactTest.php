<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppStartContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_contact_requires_authentication(): void
    {
        $this->postJson('/api/chat/whatsapp-start-contact', [
            'name' => 'Ana Pérez',
            'phone' => '34600111222',
        ])->assertStatus(401);
    }

    public function test_start_contact_creates_crm_contact_and_normalizes_spanish_mobile(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = $this->authenticatedAdmin();

        $response = $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken)
            ->postJson('/api/chat/whatsapp-start-contact', [
                'name' => 'Ana Pérez',
                'phone' => '600 111 222',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('created', true);
        $response->assertJsonPath('contact.phone', '34600111222');
        $response->assertJsonPath('contact.name', 'Ana Pérez');

        $this->assertDatabaseHas('contacts', [
            'team_id' => $user->currentTeam->id,
            'name' => 'Ana',
            'surname' => 'Pérez',
            'phone' => '34600111222',
            'creator_id' => $user->id,
        ]);
    }

    public function test_start_contact_reuses_existing_phone_without_duplicating(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = $this->authenticatedAdmin();
        $existing = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => null,
            'name' => 'Diego',
            'surname' => 'Mascarenhas',
            'phone' => '722372858',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken)
            ->postJson('/api/chat/whatsapp-start-contact', [
                'name' => 'Otro Nombre',
                'phone' => '34722372858',
            ]);

        $response->assertOk();
        $response->assertJsonPath('created', false);
        $response->assertJsonPath('contact.id', $existing->id);
        $response->assertJsonPath('contact.name', 'Diego Mascarenhas');
        $this->assertSame(1, Contact::query()->where('team_id', $user->currentTeam->id)->count());
    }

    public function test_search_contacts_requires_authentication(): void
    {
        $this->getJson('/api/chat/whatsapp-search-contacts?q=Ana')->assertStatus(401);
    }

    public function test_search_contacts_finds_crm_matches_by_name_and_phone(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = $this->authenticatedAdmin();
        Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => null,
            'name' => 'Ana',
            'surname' => 'Pérez',
            'phone' => '34600111222',
            'email' => 'ana.perez@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-search-contacts?q=Ana')
            ->assertOk()
            ->assertJsonPath('contacts.0.name', 'Ana Pérez')
            ->assertJsonPath('contacts.0.phone', '34600111222');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-search-contacts?q=600111222')
            ->assertOk()
            ->assertJsonPath('contacts.0.name', 'Ana Pérez');
    }

    public function test_start_contact_rejects_invalid_phone(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = $this->authenticatedAdmin();

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken)
            ->postJson('/api/chat/whatsapp-start-contact', [
                'name' => 'Ana Pérez',
                'phone' => '123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    private function authenticatedAdmin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }
}
