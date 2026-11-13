<?php

namespace Tests\Feature\Api;

use App\Enums\ServerStatus;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\User;
use App\Services\Hosting\StripeHostingPlanGateway;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_list_and_detail_include_metadata(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $sync = ServiceSync::query()->create([
            'stripe_id' => 'sub_metadata_test',
            'team_id' => $team->id,
            'status' => 'active',
            'plan_name' => 'Hosting anual',
            'customer_name' => 'Lizama Abogados',
            'price_currency' => 'eur',
            'amount_total' => 99,
            'data' => [
                'metadata' => [
                    'category' => 'hosting',
                    'domain' => 'lizama.test',
                    'auto_suspend' => true,
                ],
            ],
        ]);

        $server = Server::query()->create([
            'name' => 'Odin',
            'server_url' => 'odin.test',
            'username' => 'revision',
            'control_panel' => 'cpanel',
            'success' => true,
            'status_id' => ServerStatus::Active,
            'team_id' => $team->id,
        ]);

        Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'plan' => 'revision_enthusiast',
            'data' => [
                'ip' => '66.70.189.5',
                'email' => 'hola@lizama.test',
                'disk_used' => '6039M',
                'disk_limit' => '10240M',
                'nameservers' => ['ns1.revisionalpha.com', 'ns2.revisionalpha.com'],
            ],
        ]);

        $token = $user->createToken('subscriptions')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $sync->id)
            ->assertJsonPath('data.data.0.customer_name', 'Lizama Abogados')
            ->assertJsonPath('status_counts.active', 1)
            ->assertJsonPath('status_counts.canceled', 0)
            ->assertJsonPath('status_totals.active.0.currency', 'EUR')
            ->assertJsonPath('status_totals.active.0.amount', 99)
            ->assertJsonPath('status_totals.canceled', [])
            ->assertJsonPath('categories.0.name', 'Hosting')
            ->assertJsonPath('categories.0.count', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions?category=Hosting')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $sync->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions?category=Dominios')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions?search=Lizama')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $sync->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions?status=canceled')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions/'.$sync->id)
            ->assertOk()
            ->assertJsonPath('data.metadata.domain', 'lizama.test')
            ->assertJsonPath('data.metadata.category', 'hosting')
            ->assertJsonPath('data.name', 'Hosting anual')
            ->assertJsonPath('data.hosting.domain', 'lizama.test')
            ->assertJsonPath('data.hosting.username', 'lizama')
            ->assertJsonPath('data.hosting.plan', 'revision_enthusiast')
            ->assertJsonPath('data.hosting.server', 'odin.test')
            ->assertJsonPath('data.hosting.control_panel', 'cpanel')
            ->assertJsonPath('data.hosting.web_ip', '66.70.189.5')
            ->assertJsonPath('data.hosting.contact_email', 'hola@lizama.test')
            ->assertJsonPath('data.hosting.auto_suspend', true)
            ->assertJsonPath('data.hosting.disk.used_mb', 6039)
            ->assertJsonPath('data.hosting.disk.limit_mb', 10240)
            ->assertJsonPath('data.hosting.disk.usage_percent', 59)
            ->assertJsonPath('data.hosting.cpanel_url', 'https://odin.test:2083/')
            ->assertJsonPath('data.hosting.nameservers.match', true);
    }

    public function test_subscription_list_links_the_company_when_stripe_code_is_missing(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ContactStatusSeeder::class,
        ]);
        DB::table('languages')->insert(['code' => 'es', 'name' => 'Español']);

        $enterprise = fn (array $attributes) => Enterprise::withoutEvents(
            fn () => Enterprise::factory()->forTeam($team->id)->create(array_merge([
                'type_id' => 1,
                'status_id' => 1,
                'payment_type_id' => null,
                'invoice_type_id' => null,
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
            ], $attributes)),
        );

        $byName = $enterprise([
            'name' => 'Escribania Barssamian',
            'code' => null,
            'email' => null,
        ]);
        $byEmail = $enterprise([
            'name' => 'Budabel',
            'code' => null,
            'email' => 'iazubel@shirisa.com',
        ]);
        $byContact = $enterprise([
            'name' => 'RSALUD/FESS',
            'code' => 'cus_other',
            'email' => null,
        ]);

        $named = ServiceSync::query()->create([
            'stripe_id' => 'sub_company_name',
            'team_id' => $team->id,
            'status' => 'active',
            'customer_id' => 'cus_missing_name',
            'customer_name' => 'Escribania Barssamian',
            'customer_email' => 'gabrielabarssamian@gmail.com',
        ]);
        $emailed = ServiceSync::query()->create([
            'stripe_id' => 'sub_company_email',
            'team_id' => $team->id,
            'status' => 'active',
            'customer_id' => 'cus_missing_email',
            'customer_name' => 'Budabel S.R.L.',
            'customer_email' => 'iazubel@shirisa.com',
        ]);
        Service::query()->create([
            'enterprise_id' => $byEmail->id,
            'subscription_id' => $emailed->id,
        ]);
        $person = ServiceSync::query()->create([
            'stripe_id' => 'sub_company_contact',
            'team_id' => $team->id,
            'status' => 'active',
            'customer_id' => 'cus_missing_contact',
            'customer_name' => 'maria del martinez',
            'customer_email' => 'rmartinez@rsalud.com.ar',
        ]);
        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Maria',
            'email' => 'rmartinez@rsalud.com.ar',
            'creator_id' => $user->id,
            'status_id' => 1,
            'language' => 'es',
        ]);
        $contact->enterprises()->attach($byContact->id);

        $token = $user->createToken('subscriptions-company')->plainTextToken;
        $rows = collect($this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions')
            ->assertOk()
            ->json('data.data'));

        $this->assertSame($byName->id, $rows->firstWhere('id', $named->id)['enterprise']['id']);
        $this->assertSame($byEmail->id, $rows->firstWhere('id', $emailed->id)['enterprise']['id']);
        $this->assertSame($byContact->id, $rows->firstWhere('id', $person->id)['enterprise']['id']);
    }

    public function test_subscription_metadata_can_be_changed_and_marks_the_kind(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $sync = ServiceSync::query()->create([
            'stripe_id' => 'sub_metadata_edit',
            'team_id' => $team->id,
            'status' => 'active',
            'plan_name' => 'Mantenimiento',
            'data' => [
                'category' => 'service',
                'metadata' => [
                    'category' => 'service',
                    'note' => 'viejo',
                    'auto_suspend' => 'true',
                ],
            ],
        ]);
        $gateway = new class extends StripeHostingPlanGateway
        {
            /** @var array<string, string> */
            public array $metadata = [];

            public function replaceMetadata(Team $team, string $subscriptionId, array $metadata): bool
            {
                $this->metadata = $metadata;

                return true;
            }
        };
        $this->app->instance(StripeHostingPlanGateway::class, $gateway);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('subscriptions')->plainTextToken)
            ->patchJson('/api/subscriptions/'.$sync->id.'/metadata', [
                'kind' => 'service',
                'metadata' => [
                    'note' => 'Mantenimiento web',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.kind', 'service')
            ->assertJsonPath('data.metadata.type', 'service')
            ->assertJsonPath('data.metadata.category', 'service')
            ->assertJsonPath('data.metadata.note', 'Mantenimiento web');

        $sync->refresh();
        $this->assertSame('', $gateway->metadata['auto_suspend']);
        $this->assertSame('service', $sync->data['category']);
        $this->assertSame('Mantenimiento web', $sync->data['metadata']['note']);
    }

    public function test_subscription_categories_and_service_category_can_be_updated(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([EnterpriseTypeSeeder::class, EnterpriseStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->firstOrCreate(
            ['key' => 'services'],
            [
                'name' => 'Services',
                'icon' => 'ti-server',
                'description' => null,
                'is_core' => false,
                'status' => 1,
            ],
        );

        $hosting = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
            'status' => 1,
        ]);
        $vps = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'VPS',
            'status' => 1,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'code' => 'cus_cat_1',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $sync = ServiceSync::query()->create([
            'stripe_id' => 'sub_category_edit',
            'team_id' => $team->id,
            'status' => 'active',
            'plan_name' => 'Mantenimiento web',
            'customer_id' => 'cus_cat_1',
            'customer_name' => 'Acme SL',
            'data' => [
                'category' => 'service',
                'metadata' => ['category' => 'service'],
            ],
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'subscription_id' => $sync->id,
            'category_id' => $hosting->id,
            'operation' => 'sell',
            'description' => 'Mantenimiento',
            'data' => [],
            'currency_id' => 1,
            'price' => 19.99,
            'discount' => 0,
            'frequency' => 1,
            'responsible_id' => $user->id,
            'status' => 4,
        ]);

        $token = $user->createToken('subscriptions')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $hosting->id, 'name' => 'Hosting'])
            ->assertJsonFragment(['id' => $vps->id, 'name' => 'VPS']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions/'.$sync->id)
            ->assertOk()
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.service_category_id', $hosting->id)
            ->assertJsonPath('data.service_category_name', 'Hosting');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/subscriptions/'.$sync->id.'/service-category', [
                'category_id' => $vps->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('category_id', $vps->id)
            ->assertJsonPath('category_name', 'VPS')
            ->assertJsonPath('data.service_category_id', $vps->id);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'category_id' => $vps->id,
        ]);
    }

    public function test_hosting_plan_cannot_be_changed_via_metadata(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $server = Server::query()->create([
            'name' => 'Odin',
            'server_url' => 'odin.test',
            'username' => 'revision',
            'control_panel' => 'cpanel',
            'success' => true,
            'status_id' => ServerStatus::Active,
            'team_id' => $team->id,
        ]);

        Domain::query()->create([
            'domain' => 'lizama.test',
            'server_id' => $server->id,
            'username' => 'lizama',
            'plan' => 'revision_enthusiast',
            'data' => [],
        ]);

        $sync = ServiceSync::query()->create([
            'stripe_id' => 'sub_plan_guard',
            'team_id' => $team->id,
            'status' => 'active',
            'plan_name' => 'Hosting anual',
            'data' => [
                'category' => 'hosting',
                'metadata' => [
                    'category' => 'hosting',
                    'domain' => 'lizama.test',
                    'plan' => 'revision_enthusiast',
                ],
            ],
        ]);

        $gateway = new class extends StripeHostingPlanGateway
        {
            public bool $called = false;

            public function replaceMetadata(Team $team, string $subscriptionId, array $metadata): bool
            {
                $this->called = true;

                return true;
            }
        };
        $this->app->instance(StripeHostingPlanGateway::class, $gateway);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('subscriptions')->plainTextToken)
            ->patchJson('/api/subscriptions/'.$sync->id.'/metadata', [
                'kind' => 'hosting',
                'metadata' => [
                    'domain' => 'lizama.test',
                    'plan' => 'revision_business',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cambiá el plan desde el selector de hosting; debe sincronizarse con WHM.');

        $this->assertFalse($gateway->called);
    }

    public function test_hosting_subscription_cannot_change_kind_or_service_category(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([EnterpriseTypeSeeder::class, EnterpriseStatusSeeder::class]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->firstOrCreate(
            ['key' => 'services'],
            [
                'name' => 'Services',
                'icon' => 'ti-server',
                'description' => null,
                'is_core' => false,
                'status' => 1,
            ],
        );
        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Email',
            'status' => 1,
        ]);
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'code' => 'cus_host_lock',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $sync = ServiceSync::query()->create([
            'stripe_id' => 'sub_host_lock',
            'team_id' => $team->id,
            'status' => 'active',
            'plan_name' => 'Hosting',
            'customer_id' => 'cus_host_lock',
            'data' => [
                'category' => 'hosting',
                'metadata' => [
                    'category' => 'hosting',
                    'domain' => 'lizama.test',
                    'plan' => 'revision_enthusiast',
                ],
            ],
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'subscription_id' => $sync->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Hosting',
            'data' => [],
            'currency_id' => 1,
            'price' => 19.99,
            'discount' => 0,
            'frequency' => 1,
            'responsible_id' => $user->id,
            'status' => 4,
        ]);

        $gateway = new class extends StripeHostingPlanGateway
        {
            public bool $called = false;

            public function replaceMetadata(Team $team, string $subscriptionId, array $metadata): bool
            {
                $this->called = true;

                return true;
            }
        };
        $this->app->instance(StripeHostingPlanGateway::class, $gateway);

        $token = $user->createToken('subscriptions')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/subscriptions/'.$sync->id.'/metadata', [
                'kind' => 'service',
                'metadata' => ['domain' => 'lizama.test', 'plan' => 'revision_enthusiast'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta suscripción es de hosting y no puede pasar a otro tipo de servicio.');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/subscriptions/'.$sync->id.'/service-category', [
                'category_id' => $category->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Las suscripciones de hosting no cambian de categoría de servicio. El plan se elige en cPanel/WHM.',
            );

        $this->assertFalse($gateway->called);
    }
}
