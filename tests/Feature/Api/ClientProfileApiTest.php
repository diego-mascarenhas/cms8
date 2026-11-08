<?php

namespace Tests\Feature\Api;

use App\Helpers\PhoneHelper;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\ServiceSync;
use App\Models\StripeSubscription;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            CurrencySeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_profile_includes_headline_billing_subscription_and_invoice(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $eur = Currency::query()->where('code', 'EUR')->firstOrFail();
        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Norte Studio',
            'code' => 'cus_profile',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $client->id,
            'name' => 'Norte Studio SL',
            'identification_number' => 'B123',
            'status' => 1,
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
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_profile_hosted',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_profile_hosted',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_profile/test',
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_profile',
            'customer_id' => $client->code,
            'status' => 'active',
            'collection_method' => 'charge_automatically',
            'plan_name' => 'Hosting anual',
            'plan_interval' => 'year',
            'plan_interval_count' => 1,
            'price_currency' => 'eur',
            'amount_total' => 49,
        ]);

        $token = $user->createToken('client-profile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/enterprises/'.$client->id.'/profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Norte Studio')
            ->assertJsonPath('data.headline.0.kicker', 'CAC')
            ->assertJsonPath('data.headline.0.tone', 'success')
            ->assertJsonPath('data.headline.1.kicker', 'LTV')
            ->assertJsonPath('data.headline.1.tone', 'info')
            ->assertJsonPath('data.headline.1.value', '30.00 €')
            ->assertJsonPath('data.headline.2.tone', 'warning')
            ->assertJsonPath('data.headline.2.value', '10.00 €')
            ->assertJsonPath('data.billing.0.name', 'Norte Studio SL')
            ->assertJsonPath('data.subscriptions.0.name', 'Hosting anual')
            ->assertJsonPath('data.invoices.0.number', '0005-1')
            ->assertJsonPath('data.invoices.0.url', 'https://invoice.stripe.com/i/acct_profile/test');
    }

    public function test_profile_uses_stripe_customer_when_the_company_has_no_billing_address(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        Country::query()->updateOrCreate(['id' => 32], ['name' => 'Argentina', 'code' => 'AR']);

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Alicia Luecero',
            'code' => 'cus_config',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        ServiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_config',
            'customer_id' => 'cus_config',
            'customer_name' => 'Alicia B. Lucero',
            'customer_email' => 'constelaciones.alicialucero@gmail.com',
            'customer_country' => 'AR',
            'status' => 'active',
            'raw_payload' => [
                'customer' => [
                    'phone' => '+5491156562935',
                    'tax_exempt' => 'exempt',
                ],
            ],
        ]);

        $token = $user->createToken('client-profile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/enterprises/'.$client->id.'/profile')
            ->assertOk()
            ->assertJsonPath('data.billing.0.name', 'Alicia B. Lucero')
            ->assertJsonPath('data.billing.0.email', 'constelaciones.alicialucero@gmail.com')
            ->assertJsonPath('data.billing.0.phone', PhoneHelper::formatForDisplayReadable('+5491156562935'))
            ->assertJsonPath('data.billing.0.country', 'Argentina')
            ->assertJsonPath('data.billing.0.tax_status', __('tax_status.Exempt from tax') === 'tax_status.Exempt from tax' ? 'Exento de impuestos' : __('tax_status.Exempt from tax'))
            ->assertJsonPath('data.billing.0.active', true);
    }
}
