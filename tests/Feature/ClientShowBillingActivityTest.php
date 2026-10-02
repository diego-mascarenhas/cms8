<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Models\StripeSubscription;
use App\Models\User;
use App\Services\Stripe\SubscriptionScheduleReader;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientShowBillingActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            CurrencySeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_client_show_lists_subscriptions_and_consumptions_apart_from_services(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Clean Up',
            'code' => 'cus_client_block',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $other = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Otro cliente',
            'code' => 'cus_other_block',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $domainSync = StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_domain_block',
            'customer_id' => $client->code,
            'status' => 'canceled',
            'plan_name' => 'Actualizado 12/2025',
            'plan_interval' => 'year',
            'plan_interval_count' => 1,
            'price_currency' => 'ars',
            'amount_total' => 40000,
            'current_period_end' => '2027-07-31',
            'raw_payload' => [
                'description' => 'Dominio CLEANUPBUENOSAIRES.COM',
            ],
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_other_block',
            'customer_id' => $other->code,
            'status' => 'active',
            'plan_name' => 'Plan de otro cliente',
            'plan_interval' => 'month',
            'price_currency' => 'eur',
            'amount_total' => 10,
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $client->id,
            'subscription_id' => $domainSync->id,
            'operation' => 'sell',
            'description' => 'Actualizado 12/2025',
            'data' => [],
            'currency_id' => 1,
            'price' => 40000,
            'discount' => 0,
            'frequency' => 1,
            'status' => 4,
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $client->id,
            'subscription_id' => null,
            'operation' => 'sell',
            'description' => 'Renovación de dominio local',
            'data' => [],
            'currency_id' => 1,
            'price' => 0,
            'discount' => 0,
            'frequency' => 12,
            'status' => 4,
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_usage_block',
            'customer_id' => $client->code,
            'number' => '0005-9001',
            'status' => 'paid',
            'billing_reason' => 'manual',
            'currency' => 'eur',
            'total' => 2.53,
            'paid' => true,
            'invoice_created_at' => '2026-09-01 10:00:00',
            'raw_payload' => [
                'lines' => [
                    'data' => [
                        ['description' => 'Tokens IA · Agosto 2026'],
                    ],
                ],
            ],
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_cycle_block',
            'customer_id' => $client->code,
            'number' => '0005-9002',
            'status' => 'paid',
            'billing_reason' => 'subscription_cycle',
            'currency' => 'ars',
            'total' => 15918.88,
            'paid' => true,
            'invoice_created_at' => '2026-09-22 10:00:00',
            'raw_payload' => [
                'lines' => [
                    'data' => [
                        ['description' => 'Cuota que no es consumo'],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('client.show', $client->id));

        $response->assertOk();
        $response->assertSee('Suscripciones');
        $response->assertSee('id="clientConsumptionsBlock"', false);
        $response->assertDontSee('Suscripciones y consumos');
        $response->assertSee('https://dashboard.stripe.com/customers/cus_client_block', false);
        $response->assertSee('Dominio CLEANUPBUENOSAIRES.COM');
        $response->assertSee('Anual');
        $response->assertSee('Tokens IA · Agosto 2026');
        $response->assertSee('Renovación de dominio local');
        $response->assertDontSee('Actualizado 12/2025');
        $response->assertDontSee('Plan de otro cliente');
        $response->assertDontSee('Cuota que no es consumo');
        $response->assertSeeInOrder([
            'Suscripciones',
            'Dominio CLEANUPBUENOSAIRES.COM',
            'Consumos',
            'Tokens IA · Agosto 2026',
            'Servicios',
            'Renovación de dominio local',
        ]);
    }

    public function test_hosting_subscriptions_link_to_the_matching_hosting_account(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Clean Up',
            'code' => 'cus_hosting_link',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $server = Server::factory()->create(['team_id' => $team->id]);
        $hosting = Domain::factory()->create([
            'server_id' => $server->id,
            'domain' => 'cleanupbuenosaires.com',
        ]);
        $metadataHosting = Domain::factory()->create([
            'server_id' => $server->id,
            'domain' => 'from-metadata.com',
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_hosting_name',
            'customer_id' => $client->code,
            'status' => 'active',
            'plan_name' => 'Actualizado 12/2025',
            'plan_interval' => 'month',
            'price_currency' => 'eur',
            'amount_total' => 21.99,
            'raw_payload' => [
                'description' => 'Hosting CLEANUPBUENOSAIRES.COM',
            ],
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_hosting_meta',
            'customer_id' => $client->code,
            'status' => 'active',
            'plan_name' => 'Plan con metadata',
            'plan_interval' => 'month',
            'price_currency' => 'eur',
            'amount_total' => 10,
            'data' => [
                'category' => 'hosting',
                'domain' => 'from-metadata.com',
            ],
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_domain_only',
            'customer_id' => $client->code,
            'status' => 'canceled',
            'plan_name' => 'Actualizado 12/2025',
            'plan_interval' => 'year',
            'price_currency' => 'ars',
            'amount_total' => 40000,
            'raw_payload' => [
                'description' => 'Dominio CLEANUPBUENOSAIRES.COM',
            ],
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_vps',
            'customer_id' => $client->code,
            'status' => 'active',
            'plan_name' => 'Hosting VPS.EXAMPLE.COM',
            'plan_interval' => 'month',
            'price_currency' => 'eur',
            'amount_total' => 30,
            'data' => [
                'category' => 'vps',
                'domain' => 'cleanupbuenosaires.com',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('client.show', $client->id));

        $response->assertOk();
        $response->assertSee(
            '<a href="'.route('domain.show', $hosting->id).'" class="text-decoration-none">Hosting CLEANUPBUENOSAIRES.COM</a>',
            false,
        );
        $response->assertSee(
            '<a href="'.route('domain.show', $metadataHosting->id).'" class="text-decoration-none">Plan con metadata</a>',
            false,
        );
        $response->assertDontSee('>Dominio CLEANUPBUENOSAIRES.COM</a>', false);
        $response->assertDontSee('>Hosting VPS.EXAMPLE.COM</a>', false);
    }

    public function test_not_started_subscription_schedules_appear_in_the_billing_block(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Clean Up',
            'code' => 'cus_schedule_block',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_domain_ending',
            'customer_id' => $client->code,
            'status' => 'canceled',
            'plan_interval' => 'year',
            'price_currency' => 'ars',
            'amount_total' => 40000,
            'current_period_end' => '2027-07-31 22:00:00',
            'raw_payload' => [
                'description' => 'Dominio CLEANUPBUENOSAIRES.COM',
            ],
        ]);

        $schedule = new ServiceSync([
            'stripe_id' => 'sub_sched_1UIWugRwN51ygFdezw0mwwr5',
            'status' => 'not_started',
            'plan_interval' => 'year',
            'plan_interval_count' => 1,
            'price_currency' => 'eur',
            'amount_total' => 24,
            'current_period_end' => '2027-07-31 22:00:00',
            'raw_payload' => [
                'description' => 'Dominio CLEANUPBUENOSAIRES.COM',
            ],
        ]);

        $this->mock(SubscriptionScheduleReader::class, function ($mock) use ($schedule): void
        {
            $mock->shouldReceive('upcomingForCustomer')->once()->andReturn(collect([$schedule]));
        });

        $response = $this->actingAs($user)->get(route('client.show', $client->id));

        $response->assertOk();
        $response->assertSee('Programada', false);
        $response->assertSee('24.00');
        $response->assertSee('EUR');
        $response->assertSee('01/08/2027');
        $response->assertSeeInOrder([
            'Programada',
            'Cancelada',
        ]);
        $response->assertSee('<th class="text-center">Frecuencia</th>', false);
        $response->assertSee('<th class="text-end">Próxima</th>', false);
        $response->assertDontSee('>Descuento</th>', false);
    }

    public function test_subscription_discount_column_appears_only_when_a_discount_exists(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Clean Up',
            'code' => 'cus_discount_block',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_with_coupon',
            'customer_id' => $client->code,
            'status' => 'active',
            'plan_interval' => 'month',
            'price_currency' => 'eur',
            'amount_total' => 21.99,
            'raw_payload' => [
                'description' => 'Hosting con cupón',
                'discounts' => [
                    ['coupon' => ['percent_off' => 15]],
                ],
            ],
        ]);

        $serviceSync = StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_with_service_discount',
            'customer_id' => $client->code,
            'status' => 'active',
            'plan_interval' => 'year',
            'price_currency' => 'eur',
            'amount_total' => 24,
            'raw_payload' => [
                'description' => 'Dominio con descuento',
            ],
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $client->id,
            'subscription_id' => $serviceSync->id,
            'operation' => 'sell',
            'description' => 'Dominio con descuento',
            'data' => [],
            'currency_id' => 1,
            'price' => 24,
            'discount' => 10,
            'frequency' => 12,
            'status' => 4,
        ]);

        $this->mock(SubscriptionScheduleReader::class, function ($mock): void
        {
            $mock->shouldReceive('upcomingForCustomer')->andReturn(collect());
        });

        $response = $this->actingAs($user)->get(route('client.show', $client->id));

        $response->assertOk();
        $response->assertSee('<th class="text-end">Descuento</th>', false);
        $response->assertSee('15%');
        $response->assertSee('10%');
        $response->assertSee('Hosting con cupón');
    }

    public function test_billing_row_shows_the_stripe_customer_country_when_the_address_has_none(): void
    {
        $this->seed(EnterpriseTaxStatusTypeSeeder::class);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Country::query()->create([
            'id' => 32,
            'name' => 'Argentina',
            'code' => 'ar',
        ]);

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Clean Up',
            'code' => 'cus_country_block',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $client->id,
            'name' => 'CLEAN UP BUENOS AIRES SRL',
            'identification_number' => '30717198561',
            'country' => null,
            'status' => 1,
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_country_block',
            'customer_id' => $client->code,
            'customer_country' => 'AR',
            'status' => 'active',
            'plan_interval' => 'month',
            'price_currency' => 'eur',
            'amount_total' => 21.99,
            'raw_payload' => [
                'description' => 'Hosting CLEANUPBUENOSAIRES.COM',
            ],
        ]);

        $this->mock(SubscriptionScheduleReader::class, function ($mock): void
        {
            $mock->shouldReceive('upcomingForCustomer')->andReturn(collect());
        });

        $this->actingAs($user)
            ->get(route('client.show', $client->id))
            ->assertOk()
            ->assertSee('CLEAN UP BUENOS AIRES SRL')
            ->assertSee('Argentina')
            ->assertSee('Exento de impuestos')
            ->assertDontSee('Exempt from tax')
            ->assertDontSee('>AR<', false);
    }

    public function test_client_header_shows_lifetime_value_debt_and_payment_method(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $eur = Currency::query()->where('code', 'EUR')->firstOrFail();

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Clean Up',
            'code' => 'cus_headline_block',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        Invoice::query()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'operation' => 'sell',
            'number' => '0005-1',
            'date' => '2026-09-01',
            'gross_amount' => 40,
            'total_amount' => 40,
            'balance' => 10,
            'status' => 1,
            'type_id' => 1,
            'currency_id' => $eur->id,
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_headline_block',
            'customer_id' => $client->code,
            'status' => 'active',
            'collection_method' => 'charge_automatically',
            'plan_interval' => 'month',
            'price_currency' => 'eur',
            'amount_total' => 21.99,
            'raw_payload' => [
                'description' => 'Hosting CLEANUPBUENOSAIRES.COM',
                'default_payment_method' => [
                    'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2028],
                ],
            ],
        ]);

        $this->mock(SubscriptionScheduleReader::class, function ($mock): void
        {
            $mock->shouldReceive('upcomingForCustomer')->andReturn(collect());
        });

        $this->actingAs($user)
            ->get(route('client.show', $client->id))
            ->assertOk()
            ->assertSee('CAC')
            ->assertSee('0 €')
            ->assertSee('LTV')
            ->assertSee('30.00 €')
            ->assertSee('Debe')
            ->assertSee('10.00 €')
            ->assertSee('Medio de pago')
            ->assertSee('Visa ···· 4242')
            ->assertSee('Vence 08/2028')
            ->assertDontSee('ARS');
    }
}
