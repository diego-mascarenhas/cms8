<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImageService;
use App\Services\TeamStorageUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopProductImageApiTest extends TestCase
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
    private function adminWithProductsModule(bool $enableModule = true): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'products'],
            [
                'name' => 'Products',
                'icon' => 'package',
                'description' => 'Products',
                'is_core' => false,
                'status' => 1,
            ],
        );

        if ($enableModule)
        {
            $team->enableModule('products');
        }

        return [$user->fresh(), $team->fresh(), $user->createToken('idoneo-shop-images')->plainTextToken];
    }

    public function test_upload_creates_standard_market_sizes_with_wxh_filenames(): void
    {
        Storage::fake('public');

        [, $team, $token] = $this->adminWithProductsModule();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/shop/products/images', [
                'name' => 'Camiseta básica',
                'file' => UploadedFile::fake()->image('foto.png', 1600, 1200),
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original.filename', 'camiseta-basica.png')
            ->assertJsonPath('data.sizes.0.filename', 'camiseta-basica-thumb-300x300.png')
            ->assertJsonPath('data.sizes.0.key', 'thumb')
            ->assertJsonPath('data.sizes.1.filename', 'camiseta-basica-square-1080x1080.png')
            ->assertJsonPath('data.sizes.2.filename', 'camiseta-basica-landscape-1200x630.png')
            ->assertJsonPath('data.sizes.3.filename', 'camiseta-basica-portrait-1080x1350.png');

        $this->assertStringContainsString('-square-1080x1080.', (string) $response->json('data.image'));

        $sizes = collect($response->json('data.sizes'));
        $this->assertSame(
            array_keys(ProductImageService::SIZES),
            $sizes->pluck('key')->all(),
        );

        foreach ($sizes as $size)
        {
            Storage::disk('public')->assertExists($size['path']);
            $this->assertStringStartsWith('shop/products/'.$team->id.'/', $size['path']);
            $absolute = Storage::disk('public')->path($size['path']);
            $info = getimagesize($absolute);
            $this->assertSame($size['width'], $info[0] ?? null);
            $this->assertSame($size['height'], $info[1] ?? null);
        }

        Storage::disk('public')->assertExists((string) $response->json('data.original.path'));

        $product = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Camiseta básica',
            'image' => $response->json('data.image'),
        ]);

        $this->assertSame(5, $product->media()->count());
        $this->assertSame(Product::class, $product->media()->first()->model_type);
        $this->assertGreaterThan(0, TeamStorageUsageStatsService::bytesForTeam((int) $team->id));
    }

    public function test_upload_auto_enables_products_module(): void
    {
        Storage::fake('public');

        [, $team, $token] = $this->adminWithProductsModule(false);

        $this->assertFalse($team->hasModule('products'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/shop/products/images', [
                'file' => UploadedFile::fake()->image('foto.jpg', 400, 400),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertTrue($team->fresh()->hasModule('products'));
    }

    public function test_variants_for_url_rewrites_sibling_sizes(): void
    {
        $url = 'https://cms8.test/storage/shop/products/1/abc/remera-square-1080x1080.jpg';

        $variants = app(ProductImageService::class)->variantsForUrl($url);

        $this->assertCount(count(ProductImageService::SIZES), $variants);
        $this->assertSame('remera-thumb-300x300.jpg', $variants[0]['filename']);
        $this->assertSame(
            'https://cms8.test/storage/shop/products/1/abc/remera-landscape-1200x630.jpg',
            collect($variants)->firstWhere('key', 'landscape')['url'],
        );
    }

    public function test_import_downloads_a_remote_photo_into_the_team_folder(): void
    {
        Storage::fake('public');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $photo = UploadedFile::fake()->image('foto.jpg', 80, 80);
        Http::fake([
            'https://images.example.test/*' => Http::response(
                (string) file_get_contents($photo->getPathname()),
                200,
                ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $product = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Camiseta básica',
            'image' => 'https://images.example.test/camiseta.jpg',
        ]);

        $result = app(ProductImageService::class)->importProduct($product->fresh());

        $this->assertSame('imported', $result['status']);
        $this->assertStringContainsString('shop/products/'.$team->id.'/', (string) $result['image']);
        $this->assertStringContainsString('-square-1080x1080.', (string) $product->fresh()->image);
        $this->assertGreaterThan(0, TeamStorageUsageStatsService::bytesForTeam((int) $team->id));

        $again = app(ProductImageService::class)->importProduct($product->fresh());
        $this->assertSame('registered', $again['status']);
        $this->assertSame(5, $product->fresh()->media()->count());
    }
}
