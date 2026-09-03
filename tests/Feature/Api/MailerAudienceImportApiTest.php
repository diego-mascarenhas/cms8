<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailerAudienceImportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'Contacts',
                'is_core' => false,
                'status' => 1,
            ],
        );
        Module::query()->firstOrCreate(
            ['key' => 'mailer'],
            [
                'name' => 'Mailer',
                'icon' => 'mail',
                'description' => 'Mailer',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('mailer');
        $team->enableModule('contacts');

        return [$user, $team->fresh(), $user->createToken('idoneo-mailer-audience-import')->plainTextToken];
    }

    public function test_guest_cannot_see_import_schema(): void
    {
        $this->getJson('/api/mailer/audience/import')->assertUnauthorized();
    }

    public function test_schema_includes_columns_and_sample_csv(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience/import')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.required_columns.0', 'email')
            ->assertJsonPath('data.contacts_count', 0);

        $sample = (string) $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience/import')
            ->json('data.sample_csv');

        $this->assertStringContainsString('email,name,surname,phone,categories', $sample);
        $this->assertStringContainsString('lucia.garcia@cliente.com', $sample);
    }

    public function test_import_creates_and_updates_contacts_and_lists(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        $existing = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Lucía',
            'surname' => 'Vieja',
            'email' => 'lucia.garcia@cliente.com',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);

        $csv = <<<'CSV'
email,name,surname,phone,categories
lucia.garcia@cliente.com,Lucía,García,+34600111222,Newsletter
martin.perez@cliente.com,Martín,Pérez,+34600999888,Newsletter|VIP
CSV;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('audience.csv', $csv),
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.contacts_count', 2);

        $this->assertDatabaseHas('contacts', [
            'id' => $existing->id,
            'surname' => 'García',
            'phone' => '+34600111222',
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'martin.perez@cliente.com',
            'name' => 'Martín',
        ]);
        $this->assertDatabaseHas('categories', [
            'team_id' => $team->id,
            'name' => 'Newsletter',
        ]);
        $this->assertDatabaseHas('categories', [
            'team_id' => $team->id,
            'name' => 'VIP',
        ]);

        $martin = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'martin.perez@cliente.com')
            ->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['Newsletter', 'VIP'],
            $martin->categories()->pluck('name')->all(),
        );
    }

    public function test_import_accepts_spanish_headers_and_semicolon(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $csv = "correo;nombre;listas\nana@cliente.com;Ana;Clientes\n";

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('audience.csv', $csv),
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'ana@cliente.com',
            'name' => 'Ana',
        ]);
        $this->assertDatabaseHas('categories', [
            'team_id' => $team->id,
            'name' => 'Clientes',
        ]);
    }

    public function test_import_skips_invalid_emails_and_missing_columns(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('audience.csv', "nombre,apellido\nAna,Pérez\n"),
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.created', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('audience.csv', "email,name\nno-es-email,Ana\n"),
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.skipped', 1);
    }

    public function test_import_rejects_a_non_csv_upload(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
