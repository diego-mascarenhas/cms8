<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactWriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_team_contact_and_import_reads_a_csv(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(ContactStatusSeeder::class);
        $this->seed(LanguageSeeder::class);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Country::query()->updateOrCreate(
            ['id' => 32],
            ['name' => 'Argentina', 'code' => 'AR'],
        );

        $module = Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            ['name' => 'Contacts', 'description' => 'Contacts', 'is_core' => 1, 'status' => 1, 'order' => 0],
        );
        $category = Category::query()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Lead web',
            'status' => 1,
            'order' => 0,
        ]);

        $token = $user->createToken('contacts')->plainTextToken;

        $this->postJson('/api/contacts', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'category_id' => $category->id,
        ])->assertUnauthorized();

        $created = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/contacts', [
                'name' => 'Ana',
                'email' => 'Ana@Example.com',
                'phone' => '+54 11 5555 0101',
                'category_id' => $category->id,
            ]);

        $created->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'ana@example.com');

        $contact = Contact::query()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame($team->id, $contact->team_id);
        $this->assertSame($user->id, $contact->creator_id);
        $this->assertTrue($contact->categories()->where('categories.id', $category->id)->exists());

        $schema = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/contacts/import');

        $schema->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.required_columns.0', 'email');

        $csv = "email,name\nnuevo@example.com,Nuevo\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $preview = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/contacts/import', [
                'file' => $file,
                'preview' => '1',
                'country_id' => 32,
            ], [
                'Accept' => 'application/json',
            ]);

        $preview->assertOk()
            ->assertJsonPath('data.created', 1);

        $import = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/contacts/import', [
                'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
                'country_id' => 32,
                'category_ids' => json_encode([$category->id]),
            ], [
                'Accept' => 'application/json',
            ]);

        $import->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'nuevo@example.com',
            'name' => 'Nuevo',
        ]);
    }
}
