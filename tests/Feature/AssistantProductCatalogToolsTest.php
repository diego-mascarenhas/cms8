<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Enums\ProductCatalogStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentConversationContextService;
use App\Services\Assistant\AssistantActorContextService;
use App\Services\AssistantToolsService;
use App\Services\ChatAssistantReplyService;
use App\Services\ShoppingCartService;
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
        $this->assertStringContainsString('25.00', $list);

        $search = $service->execute('search_products', ['query' => 'CAM-DEMO']);
        $this->assertStringContainsString('CAM-DEMO', $search);
        $this->assertStringContainsString((string) $product->id, $search);

        foreach (range(1, 9) as $index)
        {
            Product::withoutGlobalScope('team')->create([
                'team_id' => $team->id,
                'name' => 'Extra catalog '.$index,
                'code' => 'EXTRA-'.$index,
                'description' => 'Bulk',
                'price' => 5 + $index,
                'currency_id' => $currencyId,
                'category_id' => $category->id,
                'status' => true,
                'whatsapp_enabled' => true,
            ]);
        }

        $overview = $service->execute('list_product_catalog', []);
        $this->assertStringContainsString('Ropa', $overview);
        $this->assertStringContainsString('Do not list them all', $overview);
        $this->assertStringNotContainsString('Extra catalog 1', $overview);

        $filtered = $service->execute('list_product_catalog', ['category_name' => 'Ropa']);
        $this->assertStringContainsString('Camiseta demo', $filtered);
        $this->assertStringContainsString('Showing 8 of', $filtered);
    }

    public function test_catalog_lists_published_shop_products_even_without_whatsapp_flag(): void
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
            'name' => 'Shop',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Taza shop',
            'code' => 'TAZA-SHOP',
            'description' => 'Shop',
            'price' => 12.00,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'catalog_status' => ProductCatalogStatus::Publish,
            'whatsapp_enabled' => false,
        ]);

        Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Borrador shop',
            'code' => 'DRAFT-SHOP',
            'description' => 'Draft',
            'price' => 9.00,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'catalog_status' => ProductCatalogStatus::Draft,
            'whatsapp_enabled' => true,
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, '5491111223344');

        $list = $service->execute('list_product_catalog', []);
        $this->assertStringContainsString('Taza shop', $list);
        $this->assertStringContainsString('TAZA-SHOP', $list);
        $this->assertStringNotContainsString('Borrador shop', $list);
    }

    public function test_search_products_matches_natural_language_auto_parts(): void
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

        $bujia = Category::query()->create([
            'name' => 'Bujia',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);
        $abrazadera = Category::query()->create([
            'name' => 'Abrazadera',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 1,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $this->assertNotNull($currencyId);

        $gol = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'BUJIA 3 ELECT VW GOL POLO QUANTUM SAVEIRO 1.0 1.6 1.8 2.0 8V',
            'code' => '28356',
            'barcode' => '7790001000081',
            'oem' => 'BKR6EIX-11',
            'description' => 'BUJIA 3 ELECT VW GOL. Marca: NGK.',
            'price' => 12047.85,
            'currency_id' => $currencyId,
            'category_id' => $bujia->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
        $bora = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'BUJIA 3 ELECT VW BORA 2.0 CADDY 1.6',
            'code' => '25405',
            'description' => 'BUJIA 3 ELECT VW BORA. Marca: NGK.',
            'price' => 8984.98,
            'currency_id' => $currencyId,
            'category_id' => $bujia->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);
        Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'ABRAZADERA 12 X 22 (MARCA PERFECTO) 9MM',
            'code' => '25259',
            'description' => 'ABRAZADERA 12 X 22. Marca: PERFECTO.',
            'price' => 0,
            'currency_id' => $currencyId,
            'category_id' => $abrazadera->id,
            'catalog_status' => 'draft',
            'status' => false,
            'whatsapp_enabled' => true,
        ]);
        $otherClamp = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'ABRAZADERA 16 X 27 (MARCA PERFECTO) 9MM',
            'code' => '21861',
            'description' => 'ABRAZADERA 16 X 27. Marca: PERFECTO.',
            'price' => 989.93,
            'currency_id' => $currencyId,
            'category_id' => $abrazadera->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, '5491111223344');

        $golSearch = $service->execute('search_products', ['query' => 'bujía para un gol']);
        $this->assertStringContainsString('28356', $golSearch);
        $this->assertStringContainsString((string) $gol->id, $golSearch);
        $this->assertStringNotContainsString('25405', $golSearch);
        $this->assertStringContainsString('Matches:', $golSearch);

        $boraSearch = $service->execute('search_products', ['query' => 'Una bujía para un bora']);
        $this->assertStringContainsString('25405', $boraSearch);
        $this->assertStringContainsString((string) $bora->id, $boraSearch);

        $clampSearch = $service->execute('search_products', ['query' => 'Una abrazadera 12 x 22']);
        $this->assertStringContainsString('21861', $clampSearch);
        $this->assertStringContainsString((string) $otherClamp->id, $clampSearch);
        $this->assertStringContainsString('Closest published products', $clampSearch);
        $this->assertStringNotContainsString('25259', $clampSearch);

        $barcodeSearch = $service->execute('search_products', ['query' => '7790001000081']);
        $this->assertStringContainsString('28356', $barcodeSearch);
        $this->assertStringContainsString('7790001000081', $barcodeSearch);

        $oemSearch = $service->execute('search_products', ['query' => 'BKR6EIX-11']);
        $this->assertStringContainsString('28356', $oemSearch);
        $this->assertStringContainsString('BKR6EIX-11', $oemSearch);
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

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, $phone);

        $msg = $service->execute('add_to_whatsapp_cart', ['product_code' => 'ZAP-99', 'quantity' => 2]);
        $this->assertStringContainsString('Zapato test', $msg);

        $lines = app(ShoppingCartService::class)->whatsAppLines((int) $team->id, $phone);
        $this->assertSame(1, $lines->count());
        $item = $lines->first();
        $this->assertSame(2, (int) $item->quantity);

        $cart = $service->execute('view_whatsapp_cart', []);
        $this->assertStringContainsString('Zapato test', $cart);
        $this->assertStringContainsString('x2', $cart);
        $this->assertStringContainsString('finalizar', $cart);
    }

    public function test_add_to_whatsapp_cart_uses_last_searched_product_when_only_quantity_is_given(): void
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
            'name' => 'Repuestos',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $product = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'ABRAZADERA 16 X 27',
            'code' => '21870',
            'description' => 'Perfecto',
            'price' => 989.93,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $phone = '5491199988800';

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, $phone);

        $search = $service->execute('search_products', ['query' => 'Abrazadera 16 x 27']);
        $this->assertStringContainsString((string) $product->id, $search);

        $msg = $service->execute('add_to_whatsapp_cart', ['quantity' => 2]);
        $this->assertStringContainsString('ABRAZADERA 16 X 27', $msg);
        $this->assertStringContainsString('finalizar', $msg);

        $item = app(ShoppingCartService::class)->whatsAppLines((int) $team->id, $phone)->first();
        $this->assertNotNull($item);
        $this->assertSame((int) $product->id, (int) $item->id);
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

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setWhatsAppGatewayOverride($gateway);
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

    public function test_send_whatsapp_message_second_call_skipped_in_single_send_mode(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);

        $gateway = new class implements WhatsAppGateway
        {
            public int $sendCount = 0;

            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                $this->sendCount++;

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

        $this->actingAs($user);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setWhatsAppGatewayOverride($gateway);
        $service->setRequestContext($user->id, $team->id, null);
        $service->setWhatsAppToolSingleCustomerSendPerTurn(true);

        $first = $service->execute('send_whatsapp_message', [
            'phone' => '34111222333',
            'message' => 'Primero [DEMO_FLOW:demo]',
        ]);
        $second = $service->execute('send_whatsapp_message', [
            'phone' => '34111222333',
            'message' => 'Segundo',
        ]);

        $this->assertStringContainsString('WhatsApp message sent to 34111222333', $first);
        $this->assertStringContainsString('Opening WhatsApp was already sent in this turn', $second);
        $this->assertSame(1, $gateway->sendCount);
    }

    public function test_ver_carrito_reply_shows_the_real_cart_without_calling_the_model(): void
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
            'name' => 'Abrazaderas',
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
            'name' => 'ABRAZADERA 8 X 16',
            'code' => '21861',
            'description' => 'Clamp',
            'price' => 989.43,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $phone = '5491199988811';
        $carts = app(ShoppingCartService::class);
        $cart = $carts->forWhatsApp((int) $team->id, $phone);
        $carts->addProduct($cart, $product, 4);

        $service = new class(app(AssistantToolsService::class), app(\App\Services\AssistantToolIntentPromptService::class), app(AgentConversationContextService::class), app(\App\Services\CollectionAssistantContextService::class), app(\App\Services\ContactAssistantContextService::class), app(\App\Services\AssistantToolAuthorizationService::class), app(AssistantActorContextService::class), app(\App\Services\BusinessAssistantContextService::class)) extends ChatAssistantReplyService
        {
            public bool $modelCalled = false;

            public function useStub(?int $teamId = null): bool
            {
                return false;
            }

            protected function getReplyWithLaravelAi(string $message, array $history, string $instructions, array $tools = [], ?string $routedTo = null): array
            {
                $this->modelCalled = true;

                return [
                    'success' => true,
                    'text' => 'No puedo mostrar el carrito desde este canal. Continuá en WhatsApp con Repuestos Avenida (1154905633).',
                    'routed_to' => $routedTo,
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                    'meta' => [],
                ];
            }
        };

        $reply = $service->getReply(
            'Ver carrito',
            [],
            (int) $team->id,
            true,
            $user->id,
            $phone,
            null,
            null,
            false,
            AssistantActorContextService::CHANNEL_WHATSAPP,
        );

        $this->assertFalse($service->modelCalled);
        $this->assertTrue($reply['success'] ?? false);
        $this->assertStringContainsString('ABRAZADERA 8 X 16', (string) $reply['text']);
        $this->assertStringContainsString('4', (string) $reply['text']);
        $this->assertStringContainsString('finalizar', (string) $reply['text']);
        $this->assertStringNotContainsString('1154905633', (string) $reply['text']);
        $this->assertStringNotContainsString('No puedo mostrar', (string) $reply['text']);
    }

    public function test_ver_carrito_reply_does_not_send_the_customer_to_another_number_when_empty(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $phone = '5491199988812';

        $service = new class(app(AssistantToolsService::class), app(\App\Services\AssistantToolIntentPromptService::class), app(AgentConversationContextService::class), app(\App\Services\CollectionAssistantContextService::class), app(\App\Services\ContactAssistantContextService::class), app(\App\Services\AssistantToolAuthorizationService::class), app(AssistantActorContextService::class), app(\App\Services\BusinessAssistantContextService::class)) extends ChatAssistantReplyService
        {
            public function useStub(?int $teamId = null): bool
            {
                return false;
            }

            protected function getReplyWithLaravelAi(string $message, array $history, string $instructions, array $tools = [], ?string $routedTo = null): array
            {
                return [
                    'success' => true,
                    'text' => 'No puedo mostrar el carrito desde este canal. Continuá en WhatsApp (1154905633).',
                    'routed_to' => $routedTo,
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                    'meta' => [],
                ];
            }
        };

        $reply = $service->getReply(
            'Ver carrito',
            [],
            (int) $team->id,
            true,
            $user->id,
            $phone,
            null,
            null,
            false,
            AssistantActorContextService::CHANNEL_WHATSAPP,
        );

        $this->assertTrue($reply['success'] ?? false);
        $this->assertStringContainsString('vacío', mb_strtolower((string) $reply['text']));
        $this->assertStringNotContainsString('1154905633', (string) $reply['text']);
        $this->assertStringNotContainsString('No puedo mostrar', (string) $reply['text']);
    }

    public function test_catalog_omits_prices_when_store_hides_them(): void
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

        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Principal',
            'code' => 'MAIN',
            'status' => true,
            'is_main' => true,
            'data' => ['show_prices' => false],
        ]);

        $category = Category::query()->create([
            'name' => 'Encendido',
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
            'store_id' => $store->id,
            'name' => 'Bujía de iridio',
            'code' => 'ENC-030',
            'description' => 'Spark',
            'price' => 77.77,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, '5491111223344');

        $list = $service->execute('list_product_catalog', []);
        $this->assertStringContainsString('ENC-030', $list);
        $this->assertStringNotContainsString('77.77', $list);
        $this->assertStringContainsString('hides catalog prices', $list);

        $search = $service->execute('search_products', ['query' => 'bujía']);
        $this->assertStringContainsString('ENC-030', $search);
        $this->assertStringNotContainsString('77.77', $search);

        $added = $service->execute('add_to_whatsapp_cart', ['product_code' => 'ENC-030', 'quantity' => 1]);
        $this->assertStringContainsString('Bujía de iridio', $added);
        $this->assertStringNotContainsString('77.77', $added);

        $cart = $service->execute('view_whatsapp_cart', []);
        $this->assertStringContainsString('Bujía de iridio', $cart);
        $this->assertStringNotContainsString('77.77', $cart);
    }

    public function test_confirm_whatsapp_order_creates_a_shop_order_and_clears_the_cart(): void
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

        $store = Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Principal',
            'code' => 'MAIN',
            'status' => true,
            'is_main' => true,
            'data' => [
                'checkout' => [
                    'payment_methods' => [Store::CHECKOUT_PAYMENT_CASH],
                    'fulfillment_types' => [Store::CHECKOUT_FULFILLMENT_PICKUP],
                ],
            ],
        ]);

        $category = Category::query()->create([
            'name' => 'Encendido',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $product = Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'store_id' => $store->id,
            'name' => 'Bujía de iridio',
            'code' => 'ENC-030',
            'description' => 'Spark',
            'price' => 6900,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
            'status' => true,
            'whatsapp_enabled' => true,
        ]);

        $phone = '34722372858';
        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, $phone);

        $empty = $service->execute('confirm_whatsapp_order', []);
        $this->assertStringContainsString('empty', $empty);

        $service->execute('add_to_whatsapp_cart', ['product_id' => $product->id, 'quantity' => 1]);

        $out = $service->execute('confirm_whatsapp_order', [
            'fulfillment_type' => 'pickup',
            'payment_method' => 'cash',
        ]);

        $this->assertStringContainsString('Order created', $out);
        $this->assertMatchesRegularExpression('/WA-[A-Z0-9]+/', $out);
        $this->assertStringContainsString('Retiro en el local', $out);

        $order = Order::withoutGlobalScopes()->where('team_id', $team->id)->first();
        $this->assertNotNull($order);
        $this->assertSame($store->id, $order->store_id);
        $this->assertSame('pickup', $order->metadata['checkout_offered']['chosen_fulfillment'] ?? null);
        $this->assertSame('cash', $order->metadata['checkout_offered']['chosen_payment'] ?? null);

        $cart = $service->execute('view_whatsapp_cart', []);
        $this->assertStringContainsString('empty', mb_strtolower($cart));
    }
}
