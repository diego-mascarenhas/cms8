<?php

namespace Tests\Feature\Api;

use App\Models\Category;
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
            ->assertJsonPath('data.contacts_count', 0)
            ->assertJsonPath('data.default_country_id', 32)
            ->assertJsonPath('data.countries.0.code', 'AR')
            ->assertJsonPath('data.countries.0.calling_code', '54');

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
            'phone' => '34600111222',
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

    public function test_import_normalizes_phones_and_ignores_empty_values(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        $existing = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'La Parrillita',
            'email' => 'laparrillita.villaurquiza@gmail.com',
            'phone' => '111',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);
        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Desarmadero',
            'email' => 'pedidos@desarmadero.com.ar',
            'phone' => '5491111111111',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);

        $csv = <<<'CSV'
email;empresa;telefono
laparrillita.villaurquiza@gmail.com;La Parrillita;+5491145427696
info@almacendepastas.com.ar;Almacen de Pasta;2325409931
pedidos@desarmadero.com.ar;Desarmadero Bar;NULL
solyfoods@gmail.com;Solyfoods;5491156984627570
empanadas@morita.com;Empanadas Morita;+
CSV;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import?preview=1', [
                'file' => UploadedFile::fake()->createWithContent('restaurantes.csv', $csv),
                'preview' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('data.phones', 2)
            ->assertJsonPath('data.duplicates', []);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('restaurantes.csv', $csv),
            ])
            ->assertOk()
            ->assertJsonPath('data.phones', 2);

        $this->assertSame('5491145427696', (string) $existing->fresh()->phone);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'info@almacendepastas.com.ar',
            'phone' => '5492325409931',
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'pedidos@desarmadero.com.ar',
            'phone' => '5491111111111',
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'solyfoods@gmail.com',
            'phone' => null,
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'empanadas@morita.com',
            'phone' => null,
        ]);
    }

    public function test_import_asks_before_saving_duplicate_phones(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Pedimos',
            'email' => 'contacto@pedimosfacil.com',
            'phone' => null,
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);

        $csv = <<<'CSV'
email;empresa;telefono
contacto@pedimosfacil.com;Alvarez Cerveceria;5491167083133
contacto@pedimosfacil.com;Cerveceria Bravante;543515577141
ana@cliente.com;Ana;5491112345678
CSV;

        $preview = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('restaurantes.csv', $csv),
                'preview' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('data.duplicates.0.email', 'contacto@pedimosfacil.com')
            ->assertJsonPath('data.duplicates.0.options.0.phone', '5491167083133')
            ->assertJsonPath('data.duplicates.0.options.0.label', 'Alvarez Cerveceria')
            ->assertJsonPath('data.duplicates.0.options.1.phone', '5493515577141')
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseMissing('contacts', [
            'email' => 'ana@cliente.com',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('restaurantes.csv', $csv),
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.duplicates.0.email', 'contacto@pedimosfacil.com');

        $this->assertDatabaseMissing('contacts', [
            'email' => 'ana@cliente.com',
        ]);
        $this->assertDatabaseHas('contacts', [
            'email' => 'contacto@pedimosfacil.com',
            'phone' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('restaurantes.csv', $csv),
                'choices' => json_encode([
                    'contacto@pedimosfacil.com' => '5493515577141',
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('data.duplicates', [])
            ->assertJsonPath('data.phones', 2);

        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'contacto@pedimosfacil.com',
            'phone' => '5493515577141',
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'ana@cliente.com',
            'phone' => '5491112345678',
        ]);

        $this->assertNotEmpty($preview->json('data.duplicates'));
    }

    public function test_import_repairs_mac_roman_names_and_overlong_phones(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $cerveceria = "Alvarez Cervecer\u{221A}\u{2260}a";
        $fusion = "Fusi\u{221A}\u{2265}n Cervecera";
        $mas = "ALGO M\u{221A}\u{00C4}S";
        $csv = "email;empresa;telefono\n"
            .'wilfredo@resto.test;Wilfredo;341430138687'."\n"
            .'barprus@resto.test;Bar Prus;549113404481249'."\n"
            .'baguales@resto.test;Baguales;549112644417333'."\n"
            .'contacto@pedimosfacil.test;'.$cerveceria.';5491167083133'."\n"
            .'contacto@pedimosfacil.test;'.$fusion.';543515577141'."\n"
            .'pastas@resto.test;'.$mas.';3512416424'."\n"
            .'pastas@resto.test;'.$mas.';5491111111111'."\n"
            ."jose@resto.test;José Pizzería;5491112345678\n";

        $preview = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('restaurantes.csv', $csv),
                'preview' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('data.duplicates.0.options.0.label', 'Alvarez Cervecería')
            ->assertJsonPath('data.duplicates.0.options.1.label', 'Fusión Cervecera')
            ->assertJsonPath('data.duplicates.1.options.0.label', 'ALGO MÁS');

        $this->assertStringNotContainsString('√', (string) json_encode($preview->json('data.duplicates')));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('restaurantes.csv', $csv),
                'choices' => json_encode([
                    'contacto@pedimosfacil.test' => '5491167083133',
                    'pastas@resto.test' => '5493512416424',
                ]),
            ])
            ->assertOk();

        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'wilfredo@resto.test',
            'phone' => '5493414301386',
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'barprus@resto.test',
            'phone' => '5493404481249',
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'baguales@resto.test',
            'phone' => '5492644417333',
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'pastas@resto.test',
            'phone' => '5493512416424',
        ]);
        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'jose@resto.test',
            'phone' => '5491112345678',
        ]);
    }

    public function test_import_uses_the_chosen_country_and_categories(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $moduleId = Module::query()->where('key', 'contacts')->value('id');
        $category = Category::query()->create([
            'name' => 'Restaurantes',
            'module_id' => $moduleId,
            'team_id' => $team->id,
            'order' => 0,
            'status' => 1,
        ]);

        $csv = <<<'CSV'
email;telefono
ana@cliente.es;600111222
ya@resto.test;5491167083133
CSV;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('espana.csv', $csv),
                'preview' => '1',
                'country_id' => 724,
            ])
            ->assertOk()
            ->assertJsonPath('data.phones', 2)
            ->assertJsonPath('data.duplicates', []);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/mailer/audience/import', [
                'file' => UploadedFile::fake()->createWithContent('espana.csv', $csv),
                'country_id' => 724,
                'category_ids' => json_encode([$category->id]),
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 2);

        $spanish = Contact::withoutGlobalScopes()->where('email', 'ana@cliente.es')->first();
        $this->assertNotNull($spanish);
        $this->assertSame('34600111222', (string) $spanish->phone);
        $this->assertSame(724, (int) $spanish->country);
        $this->assertTrue($spanish->categories()->where('categories.id', $category->id)->exists());

        $kept = Contact::withoutGlobalScopes()->where('email', 'ya@resto.test')->first();
        $this->assertNotNull($kept);
        $this->assertSame('5491167083133', (string) $kept->phone);
        $this->assertSame(724, (int) $kept->country);
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
