<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantProductCatalogToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_search_product_catalog_tools(): void
    {
        $this->seed(CurrencySeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $module = Module::query()->create([
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
        $team->enableModule('products');

        $category = Category::query()->create([
            'name' => 'Ropa',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $product = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Camiseta demo',
            'code' => 'CAM-DEMO',
            'description' => 'Cotton',
            'price' => 25.00,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, '5491111223344');

        $list = $service->execute('list_product_catalog', []);
        $this->assertStringContainsString('CAM-DEMO', $list);
        $this->assertStringContainsString('Camiseta demo', $list);
        $this->assertStringContainsString((string) $product->id, $list);

        $search = $service->execute('search_products', ['query' => 'CAM-DEMO']);
        $this->assertStringContainsString('CAM-DEMO', $search);
        $this->assertStringContainsString((string) $product->id, $search);
    }

    public function test_add_to_whatsapp_cart_tool_uses_cart_session(): void
    {
        $this->seed(CurrencySeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $module = Module::query()->create([
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
        $team->enableModule('products');

        $category = Category::query()->create([
            'name' => 'Ropa',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Zapato test',
            'code' => 'ZAP-99',
            'description' => 'Leather',
            'price' => 99.00,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $phone = '5491199988877';
        Cart::session($phone)->clear();

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, $phone);

        $msg = $service->execute('add_to_whatsapp_cart', ['product_code' => 'ZAP-99', 'quantity' => 2]);
        $this->assertStringContainsString('Zapato test', $msg);

        Cart::session($phone);
        $this->assertSame(1, Cart::getContent()->count());
        $item = Cart::getContent()->first();
        $this->assertSame(2, (int) $item->quantity);
    }

    public function test_send_whatsapp_message_tool_is_locked_to_inbound_customer_phone_context(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $gateway = new class implements WhatsAppGateway
        {
            public ?string $lastTo = null;

            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                $this->lastTo = $to;

                return ['ok' => true];
            }

            public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
            {
                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function getQrUrl(): ?string
            {
                return null;
            }

            public function getConnectionStatus(): ?array
            {
                return ['status' => 'connected'];
            }
        };

        $service = new AssistantToolsService($gateway);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, '5491111223344');

        $blocked = $service->execute('send_whatsapp_message', [
            'phone' => '5491188877766',
            'message' => 'Hola',
        ]);
        $this->assertStringContainsString('can only reply to the current customer phone', $blocked);
        $this->assertNull($gateway->lastTo);

        $allowed = $service->execute('send_whatsapp_message', [
            'phone' => '5491111223344',
            'message' => 'Hola',
        ]);
        $this->assertStringContainsString('WhatsApp message sent to 5491111223344', $allowed);
        $this->assertSame('5491111223344', $gateway->lastTo);
    }
}
