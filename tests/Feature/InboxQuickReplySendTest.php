<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\List60;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\List60StatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InboxQuickReplySendTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_send_expands_producto_slash_for_the_customer(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);

        Http::fake([
            'http://127.0.0.1:3000/status*' => Http::response(['status' => 'connected', 'number' => '34999000111'], 200),
            'http://127.0.0.1:3000/send-message' => Http::response(['success' => true, 'id' => 'wa-producto-1'], 200),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', '34999000111');

        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Remera básica',
            'code' => 'REM-001',
            'price' => 12500,
        ]);

        $clientPhone = '34600111999';
        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Diego',
            'phone' => $clientPhone,
            'email' => 'diego.quick@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);
        $this->createRecentInbound($clientPhone);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-send', [
                'to' => $clientPhone,
                'message' => '/producto REM-001',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'quick_reply' => 'producto',
                'messages_sent' => 1,
            ]);

        Http::assertSent(function ($request): bool
        {
            $body = (string) ($request['body'] ?? $request->data()['body'] ?? '');

            return str_contains($request->url(), '/send-message')
                && str_contains($body, 'Remera básica')
                && str_contains($body, 'REM-001')
                && ! str_contains($body, '/producto');
        });
    }

    public function test_whatsapp_producto_sends_catalog_photo_as_media_caption(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);

        Http::fake([
            'http://127.0.0.1:3000/status*' => Http::response(['status' => 'connected', 'number' => '34999000111'], 200),
            'http://127.0.0.1:3000/send-media' => Http::response(['success' => true], 200),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', '34999000111');

        $photo = 'https://images.unsplash.com/photo-1594223274512-ad4803739b7c?auto=format&fit=crop&w=640&q=80';
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Bolso tote mediano',
            'code' => 'BOL-TOT-001',
            'price' => 69900,
            'image' => $photo,
        ]);

        $clientPhone = '34600111999';
        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Diego',
            'phone' => $clientPhone,
            'email' => 'diego.foto@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);
        $this->createRecentInbound($clientPhone);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-send', [
                'to' => $clientPhone,
                'message' => '/producto BOL-TOT-001',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'quick_reply' => 'producto',
                'messages_sent' => 1,
            ]);

        Http::assertSent(function ($request) use ($photo): bool
        {
            $data = $request->data();

            return str_contains($request->url(), '/send-media')
                && ($data['mediaUrl'] ?? '') === $photo
                && str_contains((string) ($data['caption'] ?? ''), 'Bolso tote mediano');
        });
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/send-message'));

        $conversation = Conversation::query()->where('to', $clientPhone)->where('direction', 'outbound')->first();
        $this->assertNotNull($conversation);
        $this->assertStringContainsString('Bolso tote mediano', (string) $conversation->body);
        $this->assertSame($photo, $conversation->media[0]['url'] ?? null);
    }

    public function test_whatsapp_recomendar_slash_is_paused(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);

        Http::fake(function ($request)
        {
            if (str_contains($request->url(), '/status'))
            {
                return Http::response(['status' => 'connected', 'number' => '34999000111'], 200);
            }

            return Http::response(['success' => true, 'id' => 'wa-onb-'.uniqid('', true)], 200);
        });

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create(['name' => 'Ana Gómez']);
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', '34999000111');

        $clientPhone = '34600111888';
        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Diego Pérez',
            'phone' => $clientPhone,
            'email' => 'diego.onboarding@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-send', [
                'to' => $clientPhone,
                'message' => '/recomendar',
            ]);

        $response->assertStatus(422)->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('pausado', (string) $response->json('error'));

        $sentBodies = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/send-message'))
            ->map(fn (array $pair): string => (string) ($pair[0]['body'] ?? ''))
            ->values();

        $this->assertCount(0, $sentBodies);
    }

    public function test_quick_replies_catalog_is_available(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $user->assignRole('admin');

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-quick-replies')
            ->assertOk()
            ->assertJsonFragment(['slash' => '/producto'])
            ->assertJsonFragment(['slash' => '/list'])
            ->assertJsonFragment(['label' => 'Lista de seguimiento'])
            ->assertJsonMissing(['slash' => '/recomendar'])
            ->assertJsonMissing(['slash' => '/accesos'])
            ->assertJsonMissing(['slash' => '/onboarding'])
            ->assertJsonMissing(['slash' => '/cbu'])
            ->assertJsonMissing(['slash' => '/horarios']);
    }

    public function test_product_suggestions_list_published_shop_items(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Remera básica',
            'code' => 'REM-001',
            'catalog_status' => \App\Enums\ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-product-suggestions?q=remera')
            ->assertOk()
            ->assertJsonPath('items.0.code', 'REM-001')
            ->assertJsonPath('items.0.name', 'Remera básica');
    }

    public function test_whatsapp_list_slash_enrolls_contact_without_sending(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);

        Http::fake([
            'http://127.0.0.1:3000/status*' => Http::response(['status' => 'connected', 'number' => '34999000111'], 200),
            'http://127.0.0.1:3000/send-message' => Http::response(['success' => true, 'id' => 'wa-list-1'], 200),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            List60StatusesSeeder::class,
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'List 60',
                'icon' => 'list',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', '34999000111');
        $team->enableModule('list60');

        $clientPhone = '34600111777';
        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Diego Lista',
            'phone' => $clientPhone,
            'email' => 'diego.list@example.com',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-send', [
                'to' => $clientPhone,
                'message' => '/list assistant',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'quick_reply' => 'list',
                'messages_sent' => 0,
            ])
            ->assertJsonFragment(['notice' => 'Diego Lista quedó en la lista de seguimiento: Assistant.']);

        $this->assertDatabaseHas('list60', [
            'contact_id' => Contact::query()->where('phone', $clientPhone)->value('id'),
        ]);
        $this->assertSame(1, List60::query()->count());

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/send-message'));
    }

    public function test_inbox_image_streams_a_public_storage_file(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Storage::fake('public');
        Storage::disk('public')->put('whatsapp-inbound/helix.jpg', 'fake-image-bytes');
        config(['app.url' => 'https://cms8.test']);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/chat/inbox-image?url='.urlencode('https://cms8.test/storage/whatsapp-inbound/helix.jpg'))
            ->assertOk()
            ->assertSee('fake-image-bytes');
    }

    public function test_inbox_image_rejects_foreign_hosts(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Storage::fake('public');
        Storage::disk('public')->put('whatsapp-inbound/helix.jpg', 'fake-image-bytes');
        config(['app.url' => 'https://cms8.test']);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/chat/inbox-image?url='.urlencode('https://evil.test/storage/whatsapp-inbound/helix.jpg'))
            ->assertNotFound();
    }

    private function createRecentInbound(string $from): void
    {
        Conversation::create([
            'message_sid' => 'wa_quick_'.uniqid(),
            'channel' => 'whatsapp',
            'from' => $from,
            'to' => '34999000111',
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);
    }
}
