<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserLinkCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_link_sets_the_contact_team_as_current_team(): void
    {
        Bus::fake();
        $this->seed([
            ContactStatusSeeder::class,
            CountrySeeder::class,
            LanguageSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $admin = User::factory()->withPersonalTeam()->create();
        $admin->assignRole('admin');
        $team = $admin->ownedTeams()->first();
        $admin->forceFill(['current_team_id' => $team->id])->save();

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Nuevo',
            'email' => 'cliente.nuevo@example.com',
            'creator_id' => $admin->id,
            'responsible_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('user-link.create', ['contact', $contact->id]), [
                'name' => 'Cliente Nuevo',
                'email' => 'cliente.nuevo@example.com',
                'password' => 'secret123',
                'role' => 'client',
            ])
            ->assertRedirect(route('contact.show', $contact->id));

        $created = User::query()->where('email', 'cliente.nuevo@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame($team->id, (int) $created->current_team_id);
        $this->assertTrue($created->teams->contains('id', $team->id));
        $this->assertSame('client', $created->teams->firstWhere('id', $team->id)?->pivot?->role);
        $this->assertSame($created->id, (int) $contact->fresh()->user_id);
    }
}
