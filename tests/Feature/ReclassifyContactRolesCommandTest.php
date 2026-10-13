<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class ReclassifyContactRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reclassify_collapses_stored_roles_and_keeps_the_title(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed([
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $statusId = (int) ContactStatus::query()->value('id');

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Luis',
            'email' => 'luis@example.com',
            'title' => 'CFO Latam Hub',
            'language' => 'es',
            'creator_id' => $user->id,
            'status_id' => $statusId,
        ]);
        Contact::withoutGlobalScopes()->whereKey($contact->id)->update(['role' => 'CFO Latam Hub']);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'title' => 'Director comercial',
            'language' => 'es',
            'creator_id' => $user->id,
            'status_id' => $statusId,
        ]);

        $person = Person::query()->create([
            'name' => 'Luis',
            'title' => 'CFO For Hbt Latam',
            'role' => 'CFO For Hbt Latam',
            'organization_name' => 'Hbt',
        ]);

        $this->artisan('contacts:reclassify-roles', ['--dry-run' => true])
            ->expectsOutput('Contacts updated: 1 (dry-run)')
            ->expectsOutput('People updated: 1 (dry-run)')
            ->assertSuccessful();

        $this->assertSame('CFO Latam Hub', $contact->fresh()->role);

        $this->artisan('contacts:reclassify-roles')
            ->expectsOutput('Contacts updated: 1')
            ->expectsOutput('People updated: 1')
            ->assertSuccessful();

        $contact->refresh();
        $this->assertSame('CFO Latam Hub', $contact->title);
        $this->assertSame('CFO', $contact->role);
        $this->assertSame(
            'Director Comercial',
            Contact::withoutGlobalScopes()->where('email', 'ana@example.com')->value('role'),
        );
        $this->assertSame('CFO', $person->fresh()->role);
        $this->assertSame('CFO For Hbt Latam', $person->fresh()->title);

        $this->artisan('contacts:reclassify-roles')
            ->expectsOutput('Contacts updated: 0')
            ->expectsOutput('People updated: 0')
            ->assertSuccessful();

        Contact::withoutGlobalScopes()->whereKey($contact->id)->update(['role' => 'CFO Latam Hub']);

        $this->artisan('start', ['--reclassify-roles' => true])
            ->expectsOutput('Contacts updated: 1')
            ->expectsOutput('People updated: 0')
            ->assertSuccessful();

        $this->assertSame('CFO', $contact->fresh()->role);
    }
}
