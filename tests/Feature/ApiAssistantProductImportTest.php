<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiAssistantProductImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Team, 2: string}
     */
    private function assistantUserWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed(ModuleSeeder::class);
        $this->seed(CurrencySeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team, $user->createToken('assistant-product-import-test')->plainTextToken];
    }

    public function test_product_import_requires_authentication(): void
    {
        $this->getJson('/api/assistant/products/import')->assertStatus(401);
    }

    public function test_schema_exposes_columns_and_a_sample_file(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/products/import')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.products_count', 0)
            ->assertJsonStructure([
                'data' => ['required_columns', 'optional_columns', 'sample_csv', 'products_count'],
            ]);

        $this->assertSame(['code', 'name', 'price', 'category'], $response->json('data.required_columns'));
        $this->assertStringContainsString('code,name,price,category', $response->json('data.sample_csv'));
        $this->assertContains('whatsapp_enabled', $response->json('data.optional_columns'));
        $this->assertSame(30, $response->json('data.demo_products'));
    }

    public function test_demo_catalogue_endpoint_returns_a_csv_with_image_urls(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/products/import/sample')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['filename', 'csv', 'products']]);

        $this->assertSame(30, $response->json('data.products'));
        $this->assertStringContainsString('https://cdn.dummyjson.com/', $response->json('data.csv'));
        $this->assertStringEndsWith('.csv', $response->json('data.filename'));
    }

    public function test_demo_catalogue_upload_imports_thirty_products(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $demo = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/products/import/sample')
            ->assertOk()
            ->json('data');

        $this->assertSame(30, $demo['products']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/assistant/products/import', [
                'file' => UploadedFile::fake()->createWithContent($demo['filename'], $demo['csv']),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 30)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.products_count', 30);

        $this->assertSame(30, Product::withoutGlobalScope('team')->where('team_id', $team->id)->count());
    }

    public function test_owner_can_import_products_from_the_assistant(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $csv = "code,name,price,category\nREM-001,Remera,12500,Indumentaria\nTAZ-002,Taza,6800,Bazar\n";

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/assistant/products/import', [
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.products_count', 2);

        $product = Product::withoutGlobalScope('team')->where('team_id', $team->id)->where('code', 'REM-001')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->whatsapp_enabled);
        $this->assertSame('publish', $product->catalog_status->value);
    }

    public function test_import_returns_422_with_row_errors_when_nothing_could_be_imported(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $csv = "code,name,price,category,currency\nBAD-001,Moneda mala,100,Bazar,XXX\n";

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/assistant/products/import', [
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonCount(1, 'data.errors');

        $this->assertSame(0, Product::withoutGlobalScope('team')->where('team_id', $team->id)->count());
    }

    public function test_import_rejects_a_non_csv_upload(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/assistant/products/import', [
                'file' => UploadedFile::fake()->image('catalog.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_owner_can_delete_all_team_products_with_password(): void
    {
        [$user, $team, $token] = $this->assistantUserWithToken();
        $this->importCsv($token, "code,name,price,category\nREM-DEL-1,Remera,10,Ropa\nTAZ-DEL-2,Taza,8,Bazar\n");

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/assistant/products', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted', 2)
            ->assertJsonPath('data.products_count', 0);

        $this->assertSame(0, Product::withoutGlobalScope('team')->where('team_id', $team->id)->count());
        $this->assertSame($user->id, $team->user_id);
    }

    public function test_delete_all_products_rejects_a_wrong_password(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        $this->importCsv($token, "code,name,price,category\nREM-KEEP,Remera,10,Ropa\n");

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/assistant/products', ['password' => 'no-es-esa'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertSame(1, Product::withoutGlobalScope('team')->where('team_id', $team->id)->count());
    }

    public function test_delete_all_products_does_not_touch_another_team(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        $other = Team::factory()->create();
        $this->importCsv($token, "code,name,price,category\nOWN-1,Propio,10,Ropa\n");

        $this->importCsv($token, "code,name,price,category\nOTH-1,Ajeno,10,Bazar\n");
        Product::withoutGlobalScope('team')->where('team_id', $team->id)->where('code', 'OTH-1')->update([
            'team_id' => $other->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/assistant/products', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertSame(0, Product::withoutGlobalScope('team')->where('team_id', $team->id)->count());
        $this->assertSame(1, Product::withoutGlobalScope('team')->where('team_id', $other->id)->count());
    }

    private function importCsv(string $token, string $csv): void
    {
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/assistant/products/import', [
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertOk();
    }
}
