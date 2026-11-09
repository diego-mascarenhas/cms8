<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Country;
use App\Models\Enterprise;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientContactRoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_inside_the_company_can_be_changed_and_cleared(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ContactStatusSeeder::class,
            LanguageSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        Country::query()->updateOrCreate(['id' => 32], ['name' => 'Argentina', 'code' => 'AR']);

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Norte Studio',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $leadId = \App\Models\ContactStatus::query()->where('name', 'Lead')->value('id');
        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'email' => 'ana@norte.test',
            'language' => 'es',
            'country' => 32,
            'creator_id' => $user->id,
            'status_id' => $leadId,
        ]);
        $contact->enterprises()->attach($client->id);

        $token = $user->createToken('role')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/enterprises/'.$client->id.'/contacts/'.$contact->id, ['role' => 'Usuario'])
            ->assertOk()
            ->assertJsonPath('role', 'Usuario');

        $this->assertSame('Usuario', $contact->enterprises()->first()->pivot->position);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/enterprises/'.$client->id.'/contacts/'.$contact->id, ['role' => ''])
            ->assertOk()
            ->assertJsonPath('role', null);

        $this->assertNull($contact->enterprises()->first()->pivot->position);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/enterprises/'.$client->id.'/contacts/'.$contact->id, ['role' => 'Gerente'])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/enterprises/'.$client->id.'/contacts/'.$contact->id, ['role' => 'Reseller'])
            ->assertStatus(422);
    }

    public function test_contact_can_be_detached_from_the_company(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ContactStatusSeeder::class,
            LanguageSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        Country::query()->updateOrCreate(['id' => 32], ['name' => 'Argentina', 'code' => 'AR']);

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Norte Studio',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $leadId = \App\Models\ContactStatus::query()->where('name', 'Lead')->value('id');
        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'email' => 'ana@norte.test',
            'language' => 'es',
            'country' => 32,
            'creator_id' => $user->id,
            'status_id' => $leadId,
            'current_enterprise_id' => $client->id,
        ]);
        $contact->enterprises()->attach($client->id, ['position' => 'Administrador']);

        $token = $user->createToken('detach')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/enterprises/'.$client->id.'/contacts/'.$contact->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse($contact->enterprises()->where('enterprises.id', $client->id)->exists());
        $this->assertNull($contact->fresh()->current_enterprise_id);
        $this->assertNotNull(Contact::query()->find($contact->id));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/enterprises/'.$client->id.'/contacts/'.$contact->id)
            ->assertNotFound();
    }
}
