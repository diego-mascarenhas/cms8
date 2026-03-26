<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppContactSheetImportService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WhatsAppContactSheetImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function attachUserToTeam(User $user, Team $team, string $role = 'editor'): void
    {
        $user->teams()->attach($team->id, ['role' => $role]);
    }

    private function sampleSheet(): string
    {
        return <<<'CSV'
Nombre,Apellido,Email,Teléfono,Empresa,Nota
Ana,García,ana@example.com,34600111222,Empresa Vinculada,Nota prueba
CSV;
    }

    public function test_returns_null_without_contact_store_prefix(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);
        $perm = Permission::firstOrCreate(['name' => 'contact.store', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        $this->assertNull(app(WhatsAppContactSheetImportService::class)->tryHandle($this->sampleSheet(), $user, (int) $team->id));
        $this->assertSame(0, Contact::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_imports_contacts_and_links_enterprise_when_empresa_matches(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Empresa Vinculada',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $user = User::factory()->create();
        $this->attachUserToTeam($user, $team);
        $perm = Permission::firstOrCreate(['name' => 'contact.store', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        $body = "contact.store\n".$this->sampleSheet();
        $reply = app(WhatsAppContactSheetImportService::class)->tryHandle($body, $user, (int) $team->id);

        $this->assertIsString($reply);
        $this->assertStringContainsString('contacto', $reply);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $team->id)->where('email', 'ana@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Ana', $contact->name);
        $this->assertNotNull($contact->current_enterprise_id);
        $this->assertTrue($contact->enterprises()->exists());
    }
}
