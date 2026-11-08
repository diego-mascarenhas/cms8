<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Country;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Service;
use App\Models\StripeSubscription;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientRosterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_lists_clients_with_consumption_login_and_renewal(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            CurrencySeeder::class,
            InvoiceTypeSeeder::class,
            ContactStatusSeeder::class,
            LanguageSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Country::query()->updateOrCreate(['id' => 32], ['name' => 'Argentina', 'code' => 'AR']);

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Norte Studio',
            'code' => 'cus_roster',
            'type_id' => 1,
            'status_id' => 1,
            'country' => 'ES',
        ]);
        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor Sur',
            'type_id' => 2,
            'status_id' => 1,
        ]);

        $member = User::factory()->create();
        $access = $member->createToken('app');
        $access->accessToken->forceFill(['last_used_at' => '2026-11-01 10:00:00'])->save();

        $leadId = \App\Models\ContactStatus::query()->where('name', 'Lead')->value('id');
        $contact = Contact::query()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'email' => 'ana@norte.test',
            'language' => 'es',
            'country' => 32,
            'creator_id' => $user->id,
            'status_id' => $leadId,
            'user_id' => $member->id,
        ]);
        $contact->enterprises()->attach($client->id);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $client->id,
            'operation' => 'sell',
            'description' => 'Hosting',
            'status' => 1,
        ]);
        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $client->id,
            'operation' => 'sell',
            'description' => 'Dominio',
            'status' => 1,
        ]);
        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $client->id,
            'subscription_id' => 9,
            'operation' => 'sell',
            'description' => 'Espejo de suscripción',
            'status' => 1,
        ]);

        StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_roster',
            'customer_id' => 'cus_roster',
            'status' => 'active',
            'plan_name' => 'Hosting anual',
            'price_currency' => 'eur',
            'amount_total' => 49,
            'current_period_end' => '2027-03-15 00:00:00',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_roster_usage',
            'customer_id' => 'cus_roster',
            'billing_reason' => 'manual',
            'currency' => 'eur',
            'total' => 3.5,
            'invoice_created_at' => '2026-10-01 00:00:00',
            'raw_payload' => [
                'lines' => [
                    'data' => [
                        ['description' => 'Tokens'],
                    ],
                ],
            ],
        ]);

        $eur = \App\Models\Currency::query()->where('code', 'EUR')->firstOrFail();
        Invoice::query()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'operation' => 'sell',
            'number' => '0001',
            'date' => '2026-10-01',
            'gross_amount' => 10,
            'total_amount' => 10,
            'balance' => 10,
            'status' => 1,
            'type_id' => 1,
            'currency_id' => $eur->id,
        ]);

        $token = $user->createToken('roster')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/enterprises/roster')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Norte Studio')
            ->assertJsonPath('data.data.0.plan', 'Hosting anual')
            ->assertJsonPath('data.data.0.amount', '49,00 EUR')
            ->assertJsonPath('data.data.0.services_count', 2)
            ->assertJsonPath('data.data.0.renewal', '2027-03-15')
            ->assertJsonPath('data.data.0.consumption', 'Tokens')
            ->assertJsonPath('data.data.0.consumption_amount', '3,50 EUR')
            ->assertJsonPath('data.data.0.balance', '10,00')
            ->assertJsonPath('data.data.0.contact.id', $contact->id)
            ->assertJsonPath('data.data.0.contact.email', 'ana@norte.test')
            ->assertJsonPath('data.data.0.last_login', '2026-11-01T10:00:00+00:00');
    }
}
