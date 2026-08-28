<?php

namespace Tests\Unit;

use App\Enums\ProductCatalogStatus;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\InboxQuickReplyService;
use App\Support\NewUserWelcomeEmailNotifier;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboxQuickReplyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_recognizes_inbox_slashes_and_ignores_outreach(): void
    {
        $service = app(InboxQuickReplyService::class);

        $this->assertSame(['key' => 'producto', 'argument' => 'REM-001'], $service->parse('/producto REM-001'));
        $this->assertSame(['key' => 'producto', 'argument' => 'SKU-99'], $service->parse('/sku SKU-99'));
        $this->assertSame(['key' => 'list', 'argument' => 'assistant'], $service->parse('/list assistant'));
        $this->assertSame(['key' => 'list', 'argument' => 'shop'], $service->parse('/lista shop'));
        $this->assertSame(['key' => 'recomendar', 'argument' => null], $service->parse('/recomendar'));
        $this->assertSame(['key' => 'onboarding', 'argument' => null], $service->parse('/onboarding'));
        $this->assertNull($service->parse('/cbu'));
        $this->assertNull($service->parse('/sucursal centro'));
        $this->assertNull($service->parse('/enviar-onboarding +34600111222'));
        $this->assertNull($service->parse('producto REM-001'));
    }

    public function test_producto_sends_published_shop_item_by_code(): void
    {
        $team = Team::factory()->create();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Remera básica',
            'code' => 'REM-001',
            'description' => 'Algodón peinado, corte regular.',
            'price' => 12500,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $resolved = app(InboxQuickReplyService::class)->resolve($team, 'producto', 'rem-001');

        $this->assertTrue($resolved['ok']);
        $this->assertStringContainsString('Remera básica', $resolved['messages'][0]);
        $this->assertStringContainsString('REM-001', $resolved['messages'][0]);
        $this->assertStringContainsString('12.500,00', $resolved['messages'][0]);
        $this->assertStringContainsString('Algodón peinado', $resolved['messages'][0]);
        $this->assertArrayNotHasKey('media', $resolved);
    }

    public function test_producto_omits_price_when_the_store_hides_prices(): void
    {
        $team = Team::factory()->create();
        Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Principal',
            'code' => 'MAIN',
            'status' => true,
            'is_main' => true,
            'data' => ['show_prices' => false],
        ]);
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Bolso tote mediano',
            'code' => 'BOL-TOT-001',
            'price' => 69900,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
            'available_in_all_stores' => true,
        ]);

        $resolved = app(InboxQuickReplyService::class)->resolve($team, 'producto', 'BOL-TOT-001');

        $this->assertTrue($resolved['ok']);
        $this->assertStringContainsString('Bolso tote mediano', $resolved['messages'][0]);
        $this->assertStringNotContainsString('Precio', $resolved['messages'][0]);
        $this->assertStringNotContainsString('69.900', $resolved['messages'][0]);
    }

    public function test_producto_includes_catalog_photo_when_present(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('shop/products/tote.jpg', 'photo');
        $team = Team::factory()->create();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Bolso tote mediano',
            'code' => 'BOL-TOT-001',
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
            'image' => 'https://images.unsplash.com/photo-1594223274512-ad4803739b7c?auto=format&fit=crop&w=640&q=80',
        ]);
        $local = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Remera',
            'code' => 'REM-FOTO',
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
            'image' => 'shop/products/tote.jpg',
        ]);

        $remote = app(InboxQuickReplyService::class)->resolve($team, 'producto', 'BOL-TOT-001');
        $stored = app(InboxQuickReplyService::class)->resolve($team, 'producto', $local->code);

        $this->assertSame(
            'https://images.unsplash.com/photo-1594223274512-ad4803739b7c?auto=format&fit=crop&w=640&q=80',
            $remote['media'] ?? null,
        );
        $this->assertSame('storage/shop/products/tote.jpg', $stored['media'] ?? null);
    }

    public function test_producto_finds_variant_sku(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Pantalón cargo',
            'code' => 'PAN-010',
            'price' => 22000,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);
        $product->defaultVariant()?->forceFill(['sku' => 'SKU-99', 'price' => 22000])->save();

        $resolved = app(InboxQuickReplyService::class)->resolve($team, 'producto', 'sku-99');

        $this->assertTrue($resolved['ok']);
        $this->assertStringContainsString('Pantalón cargo', $resolved['messages'][0]);
        $this->assertStringContainsString('SKU-99', $resolved['messages'][0]);
    }

    public function test_producto_requires_sku_or_code(): void
    {
        $team = Team::factory()->create();

        $resolved = app(InboxQuickReplyService::class)->resolve($team, 'producto', null);

        $this->assertFalse($resolved['ok']);
        $this->assertSame('Usá /producto y el SKU o código. Ej: /producto REM-001', $resolved['error']);
    }

    public function test_producto_fails_when_code_is_missing_or_draft(): void
    {
        $team = Team::factory()->create();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Borrador',
            'code' => 'DRAFT-1',
            'catalog_status' => ProductCatalogStatus::Draft,
            'status' => false,
        ]);

        $missing = app(InboxQuickReplyService::class)->resolve($team, 'producto', 'NO-EXISTE');
        $this->assertFalse($missing['ok']);
        $this->assertStringContainsString('NO-EXISTE', (string) $missing['error']);

        $draft = app(InboxQuickReplyService::class)->resolve($team, 'producto', 'DRAFT-1');
        $this->assertFalse($draft['ok']);
        $this->assertStringContainsString('borrador', (string) $draft['error']);
    }

    public function test_producto_finds_published_code_on_another_team_the_user_belongs_to(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $inboxTeam = $user->currentTeam;
        $shopTeam = Team::factory()->create();
        $user->teams()->attach($shopTeam->id, ['role' => 'admin']);
        $this->actingAs($user);

        Product::factory()->create([
            'team_id' => $shopTeam->id,
            'name' => 'Bolso tote mediano',
            'code' => 'BOL-TOT-001',
            'price' => 69900,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $resolved = app(InboxQuickReplyService::class)->resolve($inboxTeam, 'producto', 'BOL-TOT-001');

        $this->assertTrue($resolved['ok']);
        $this->assertStringContainsString('Bolso tote mediano', $resolved['messages'][0]);
        $this->assertStringContainsString('BOL-TOT-001', $resolved['messages'][0]);
    }

    public function test_producto_does_not_use_a_catalog_from_a_team_the_user_cannot_access(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        Product::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'name' => 'Ajeno',
            'code' => 'BOL-TOT-001',
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $resolved = app(InboxQuickReplyService::class)->resolve($user->currentTeam, 'producto', 'BOL-TOT-001');

        $this->assertFalse($resolved['ok']);
        $this->assertStringContainsString('BOL-TOT-001', (string) $resolved['error']);
    }

    public function test_onboarding_opens_as_idoneo_and_asks_about_pains(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Diego Pérez',
            'email' => 'diego.cliente@example.com',
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => 1,
        ]);

        $resolved = app(InboxQuickReplyService::class)->resolve($team, 'recomendar', null, $contact);

        $this->assertTrue($resolved['ok']);
        $this->assertCount(1, $resolved['messages']);
        $opening = $resolved['messages'][0];
        $this->assertStringContainsString('Hola Diego', $opening);
        $this->assertStringContainsString('Soy IDONEO, un artesano del software', $opening);
        $this->assertStringContainsString('centralizar', $opening);
        $this->assertStringContainsString('embudo', $opening);
        $this->assertStringContainsString('intención', $opening);
        $this->assertStringNotContainsString('https://assistant.idoneo.dev/register', $opening);
        $this->assertStringNotContainsString('https://shop.idoneo.dev/register', $opening);
        $this->assertStringNotContainsString('stripe', mb_strtolower($opening));
        $this->assertStringNotContainsString('49', $opening);
        $this->assertNull($contact->fresh()->user_id);
        $this->assertTrue((bool) data_get($contact->fresh()->data, 'idoneo_product_onboarding.active'));
    }

    public function test_accesos_does_not_recreate_an_existing_user(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
        $team = Team::factory()->create();
        $user = User::factory()->create(['email' => 'ya.tiene@example.com']);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'email' => 'ya.tiene@example.com',
            'name' => 'Paola',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $resolved = app(InboxQuickReplyService::class)->resolve($team, 'accesos', null, $contact);

        $this->assertTrue($resolved['ok']);
        $message = $resolved['messages'][0];
        $this->assertStringContainsString('ya.tiene@example.com', $message);
        $this->assertStringContainsString('/reset-password?', $message);
        $this->assertStringContainsString('token=', $message);
        $this->assertStringContainsString('email=', $message);
        $this->assertStringNotContainsString('Olvidé', $message);
        $this->assertStringNotContainsString('/login', $message);
        $this->assertSame($user->id, $contact->fresh()->user_id);
        $this->assertFalse(NewUserWelcomeEmailNotifier::isPlaceholderInboxEmail('ya.tiene@example.com'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'ya.tiene@example.com']);
    }

    public function test_accesos_creates_a_client_and_sends_a_reset_link_not_a_password(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'email' => 'nuevo.acceso@example.com',
            'name' => 'Nora',
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => 1,
        ]);

        $resolved = app(InboxQuickReplyService::class)->resolve($team, 'accesos', null, $contact);

        $this->assertTrue($resolved['ok']);
        $message = $resolved['messages'][0];
        $created = User::query()->where('email', 'nuevo.acceso@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('client'));
        $this->assertSame($created->id, $contact->fresh()->user_id);
        $this->assertStringContainsString('/reset-password?', $message);
        $this->assertStringContainsString('token=', $message);
        $this->assertStringNotContainsString('Olvidé', $message);
        $this->assertStringNotContainsString('spam', mb_strtolower($message));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'nuevo.acceso@example.com']);
    }
}
