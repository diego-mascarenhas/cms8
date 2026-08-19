<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\ProductCsvImportService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);

        Module::query()->create([
            'name' => 'Products',
            'key' => 'products',
            'level' => 1,
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->forceFill(['current_team_id' => $this->team->id])->save();
        $this->user->teams()->attach($this->team->id, ['role' => 'admin']);
        $this->user->assignRole($role);
    }

    public function test_admin_can_open_the_import_screen(): void
    {
        $this->actingAs($this->user->fresh())
            ->get(route('product.import'))
            ->assertOk()
            ->assertSee('code');
    }

    public function test_template_download_returns_a_csv_with_the_expected_header(): void
    {
        $response = $this->actingAs($this->user->fresh())->get(route('product.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('code,name,price,category', $response->streamedContent());
    }

    public function test_import_creates_products_and_categories(): void
    {
        $csv = <<<'CSV'
        code,name,price,description,sale_price,currency,category,catalog_status,stock_status,manage_stock,stock_quantity,size_options,color_options,whatsapp_enabled
        REM-001,Remera algodón,12500.00,Remera peinada,9900.00,ARS,Indumentaria,publish,instock,1,25,S|M|L,Negro|Blanco,1
        TAZ-002,Taza cerámica,6800,Taza 350ml,,ARS,Bazar,draft,outofstock,0,,,Blanco,0
        CSV;

        $response = $this->actingAs($this->user->fresh())->post(route('product.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ]);

        $response->assertRedirect(route('product.index'));

        $remera = Product::withoutGlobalScope('team')->where('team_id', $this->team->id)->where('code', 'REM-001')->first();
        $this->assertNotNull($remera);
        $this->assertSame('Remera algodón', $remera->name);
        $this->assertSame('12500.00', (string) $remera->price);
        $this->assertSame('9900.00', (string) $remera->sale_price);
        $this->assertSame('publish', $remera->catalog_status->value);
        $this->assertSame('instock', $remera->stock_status->value);
        $this->assertTrue($remera->manage_stock);
        $this->assertSame(25, $remera->stock_quantity);
        $this->assertSame(['S', 'M', 'L'], $remera->size_options);
        $this->assertSame(['Negro', 'Blanco'], $remera->color_options);
        $this->assertTrue($remera->whatsapp_enabled);
        $this->assertTrue($remera->status);

        $taza = Product::withoutGlobalScope('team')->where('team_id', $this->team->id)->where('code', 'TAZ-002')->first();
        $this->assertNotNull($taza);
        $this->assertNull($taza->sale_price);
        $this->assertSame('draft', $taza->catalog_status->value);
        $this->assertFalse($taza->manage_stock);
        $this->assertNull($taza->stock_quantity);
        $this->assertFalse($taza->whatsapp_enabled);
        $this->assertFalse($taza->status);

        $this->assertDatabaseHas('categories', ['team_id' => $this->team->id, 'name' => 'Indumentaria']);
        $this->assertDatabaseHas('categories', ['team_id' => $this->team->id, 'name' => 'Bazar']);

        $mainStoreId = Store::withoutGlobalScope('team')->where('team_id', $this->team->id)->where('code', 'MAIN')->value('id');
        $this->assertSame((int) $mainStoreId, (int) $remera->store_id);
    }

    public function test_import_updates_an_existing_product_matched_by_code(): void
    {
        $currencyId = (int) Currency::query()->where('code', 'ARS')->value('id');
        $category = Category::query()->create([
            'name' => 'Indumentaria',
            'module_id' => (int) Module::query()->where('key', 'products')->value('id'),
            'team_id' => $this->team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $product = Product::withoutGlobalScope('team')->create([
            'team_id' => $this->team->id,
            'name' => 'Remera vieja',
            'code' => 'REM-001',
            'description' => 'Vieja',
            'price' => 100,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $csv = "code;name;price;categoria\nREM-001;Remera nueva;1.234,56;Indumentaria\n";

        $this->actingAs($this->user->fresh())->post(route('product.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertRedirect(route('product.index'));

        $product->refresh();
        $this->assertSame('Remera nueva', $product->name);
        $this->assertSame('1234.56', (string) $product->price);
        $this->assertSame((int) $category->id, (int) $product->category_id);
        $this->assertSame(1, Product::withoutGlobalScope('team')->where('team_id', $this->team->id)->count());
        $this->assertSame(1, Category::query()->where('team_id', $this->team->id)->where('name', 'Indumentaria')->count());
    }

    public function test_import_reports_rows_with_invalid_data(): void
    {
        $csv = "code,name,price,category,currency\n,Sin código,100,Bazar,ARS\nOK-001,Producto ok,100,Bazar,ARS\nBAD-002,Moneda mala,100,Bazar,XXX\n";

        $response = $this->actingAs($this->user->fresh())->post(route('product.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ]);

        $response->assertRedirect(route('product.index'));
        $response->assertSessionHas('import_errors', function (array $errors): bool
        {
            return count($errors) === 2;
        });

        $this->assertSame(1, Product::withoutGlobalScope('team')->where('team_id', $this->team->id)->count());
        $this->assertDatabaseHas('products', ['team_id' => $this->team->id, 'code' => 'OK-001']);
    }

    public function test_import_fails_when_required_columns_are_missing(): void
    {
        $csv = "nombre,precio,categoria\nRemera,100,Bazar\n";

        $response = $this->actingAs($this->user->fresh())->post(route('product.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ]);

        $response->assertRedirect(route('product.import'));
        $response->assertSessionHas('error');
        $this->assertSame(0, Product::withoutGlobalScope('team')->where('team_id', $this->team->id)->count());
    }

    public function test_bundled_demo_catalogue_imports_cleanly_with_image_urls(): void
    {
        $path = base_path(ProductCsvImportService::DEMO_CATALOG_PATH);
        $this->assertFileExists($path);

        $result = app(ProductCsvImportService::class)->import($path, (int) $this->team->id);

        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['skipped']);
        $this->assertGreaterThanOrEqual(50, $result['created']);

        $products = Product::withoutGlobalScope('team')->where('team_id', $this->team->id)->get();
        $this->assertCount($result['created'], $products);
        $this->assertCount(0, $products->filter(fn (Product $product): bool => $product->image === null));
        $this->assertCount(
            0,
            $products->filter(fn (Product $product): bool => ! str_starts_with((string) $product->image, 'https://')),
        );
        $this->assertTrue($products->every(fn (Product $product): bool => $product->whatsapp_enabled));
        $this->assertTrue($products->every(fn (Product $product): bool => $product->status));
        $this->assertGreaterThanOrEqual(8, Category::query()->where('team_id', $this->team->id)->count());

        // Re-importing the same file must update in place, never duplicate.
        $second = app(ProductCsvImportService::class)->import($path, (int) $this->team->id);
        $this->assertSame(0, $second['created']);
        $this->assertSame($result['created'], $second['updated']);
        $this->assertSame(
            $products->count(),
            Product::withoutGlobalScope('team')->where('team_id', $this->team->id)->count(),
        );
    }

    public function test_import_rejects_a_non_csv_upload(): void
    {
        $this->actingAs($this->user->fresh())
            ->from(route('product.import'))
            ->post(route('product.import.store'), ['file' => UploadedFile::fake()->image('catalog.jpg')])
            ->assertSessionHasErrors('file');
    }
}
