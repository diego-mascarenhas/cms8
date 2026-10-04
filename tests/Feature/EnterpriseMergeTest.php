<?php

namespace Tests\Feature;

use App\Models\BillingAffiliateCommission;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\FiscalCustomerMapping;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Service;
use App\Models\TeamPassword;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnterpriseMergeTest extends TestCase
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
            ProjectStatusSeeder::class,
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_merge_keeps_the_stripe_customer_and_moves_related_records(): void
    {
        [$user, $team] = $this->adminTeam();

        $keeper = $this->enterprise($team->id, 'DOA', 'cus_TuHnnzTuf978ya', [
            'email' => 'hola@doa.test',
            'data' => ['notes' => 'cliente activo'],
        ]);
        $duplicate = $this->enterprise($team->id, 'Estudio Doa', null, [
            'phone' => '1144445555',
            'email' => 'viejo@doa.test',
            'data' => ['style_guide' => 'serif'],
        ]);

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $duplicate->id,
            'responsible_id' => $user->id,
            'name' => 'Sitio',
            'status_id' => 1,
        ]);

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $duplicate->id,
            'operation' => 'sell',
            'description' => 'Hosting',
            'data' => [],
            'currency_id' => 1,
            'price' => 10,
            'discount' => 0,
            'frequency' => 1,
            'status' => 1,
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $duplicate->id,
            'name' => 'Estudio Doa',
            'identification_number' => '30717198561',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ignacio',
            'surname' => 'Escobar',
            'email' => 'iescobar@estudiodoa.com',
            'creator_id' => $user->id,
            'current_enterprise_id' => $duplicate->id,
        ]);
        $contact->enterprises()->attach($duplicate->id, ['position' => 'Director']);

        $shared = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Ana',
            'surname' => 'Paz',
            'creator_id' => $user->id,
            'current_enterprise_id' => $keeper->id,
        ]);
        $shared->enterprises()->attach([$keeper->id, $duplicate->id]);

        FiscalCustomerMapping::query()->create([
            'team_id' => $team->id,
            'enterprise_id' => $keeper->id,
            'platform' => 'cuentica',
            'external_customer_id' => 'keeper',
        ]);
        FiscalCustomerMapping::query()->create([
            'team_id' => $team->id,
            'enterprise_id' => $duplicate->id,
            'platform' => 'cuentica',
            'external_customer_id' => 'duplicate',
        ]);
        FiscalCustomerMapping::query()->create([
            'team_id' => $team->id,
            'enterprise_id' => $duplicate->id,
            'platform' => 'other',
            'external_customer_id' => 'moved',
        ]);

        TeamPassword::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $duplicate->id,
            'name' => 'cPanel',
        ]);

        BillingAffiliateCommission::query()->create([
            'paying_team_id' => $team->id,
            'referrer_team_id' => $team->id,
            'paying_enterprise_id' => $duplicate->id,
            'stripe_invoice_id' => 'in_merge_test',
            'amount_paid_cents' => 1000,
            'currency' => 'eur',
            'commission_percent' => 10,
            'commission_amount_cents' => 100,
        ]);

        $this->actingAs($user)
            ->get(route('empresas.show', $duplicate->id))
            ->assertOk()
            ->assertSee('Fusionar');

        $this->actingAs($user)
            ->getJson(route('client.merge-candidates', $duplicate->id).'?q=DOA')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $keeper->id,
                'stripe' => true,
            ]);

        $this->actingAs($user)
            ->getJson(route('client.merge-preview', $duplicate->id).'?enterprise_id='.$keeper->id)
            ->assertOk()
            ->assertJsonPath('blocked', false)
            ->assertJsonPath('survivor_id', $keeper->id)
            ->assertJsonPath('source_id', $duplicate->id);

        $this->actingAs($user)
            ->postJson(route('client.merge', $duplicate->id), [
                'enterprise_id' => $keeper->id,
            ])
            ->assertOk()
            ->assertJsonPath('redirect', route('empresas.show', $keeper->id));

        $keeper->refresh();
        $this->assertSame('DOA', $keeper->name);
        $this->assertSame('cus_TuHnnzTuf978ya', $keeper->code);
        $this->assertSame('hola@doa.test', $keeper->email);
        $this->assertSame('1144445555', $keeper->phone);
        $this->assertSame('cliente activo', $keeper->data->notes);
        $this->assertSame('serif', $keeper->data->style_guide);

        $this->assertSoftDeleted('enterprises', ['id' => $duplicate->id]);
        $this->assertSame($keeper->id, $project->fresh()->enterprise_id);
        $this->assertSame(1, Service::withoutGlobalScopes()->where('enterprise_id', $keeper->id)->count());
        $this->assertSame(
            $keeper->id,
            EnterpriseBillingAddress::query()->where('identification_number', '30717198561')->value('enterprise_id'),
        );
        $this->assertSame($keeper->id, $contact->fresh()->current_enterprise_id);
        $this->assertTrue($contact->enterprises()->where('enterprises.id', $keeper->id)->exists());
        $this->assertSame(
            1,
            $shared->enterprises()->where('enterprises.id', $keeper->id)->count(),
        );
        $this->assertSame(
            'keeper',
            FiscalCustomerMapping::query()->where('enterprise_id', $keeper->id)->where('platform', 'cuentica')->value('external_customer_id'),
        );
        $this->assertSame(
            'moved',
            FiscalCustomerMapping::query()->where('enterprise_id', $keeper->id)->where('platform', 'other')->value('external_customer_id'),
        );
        $this->assertSame($keeper->id, TeamPassword::withoutGlobalScopes()->where('name', 'cPanel')->value('enterprise_id'));
        $this->assertSame($keeper->id, BillingAffiliateCommission::query()->where('stripe_invoice_id', 'in_merge_test')->value('paying_enterprise_id'));
    }

    public function test_merge_refuses_two_different_stripe_customers(): void
    {
        [$user, $team] = $this->adminTeam();
        $first = $this->enterprise($team->id, 'Clean Up', 'cus_TTFOX7NVHkJwYC');
        $second = $this->enterprise($team->id, 'Otra', 'cus_other');

        $this->actingAs($user)
            ->postJson(route('client.merge', $first->id), [
                'enterprise_id' => $second->id,
            ])
            ->assertStatus(422);

        $this->assertNull($first->fresh()->deleted_at);
        $this->assertNull($second->fresh()->deleted_at);
        $this->assertSame('cus_other', $second->fresh()->code);
    }

    public function test_same_tax_id_billing_address_is_collapsed(): void
    {
        [$user, $team] = $this->adminTeam();
        $keeper = $this->enterprise($team->id, 'DOA', 'cus_keep');
        $duplicate = $this->enterprise($team->id, 'Estudio Doa', null);

        $keptAddress = EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $keeper->id,
            'name' => 'Escobar Ignacio',
            'identification_number' => '20-27330401-1',
        ]);
        $duplicateAddress = EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $duplicate->id,
            'name' => 'Ignacio Escobar',
            'identification_number' => '20273304011',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $duplicate->id,
            'billing_id' => $duplicateAddress->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'A-1',
            'date' => '2026-03-01',
            'gross_amount' => 100,
            'total_amount' => 100,
            'balance' => 100,
        ]);

        $this->actingAs($user)
            ->postJson(route('client.merge', $duplicate->id), [
                'enterprise_id' => $keeper->id,
            ])
            ->assertOk();

        $invoice->refresh();
        $this->assertSame($keeper->id, $invoice->enterprise_id);
        $this->assertSame($keptAddress->id, $invoice->billing_id);
        $this->assertSoftDeleted('enterprise_billing_addresses', ['id' => $duplicateAddress->id]);
        $this->assertSame(1, EnterpriseBillingAddress::query()->where('enterprise_id', $keeper->id)->count());
    }

    public function test_without_stripe_the_open_record_stays(): void
    {
        [$user, $team] = $this->adminTeam();
        $current = $this->enterprise($team->id, 'Actual', null);
        $other = $this->enterprise($team->id, 'Otra', null, ['phone' => '1100000000']);

        $this->actingAs($user)
            ->postJson(route('client.merge', $current->id), [
                'enterprise_id' => $other->id,
            ])
            ->assertOk()
            ->assertJsonPath('redirect', route('empresas.show', $current->id));

        $this->assertSame('Actual', $current->fresh()->name);
        $this->assertSame('1100000000', $current->fresh()->phone);
        $this->assertSoftDeleted('enterprises', ['id' => $other->id]);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team}
     */
    private function adminTeam(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user, $team];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function enterprise(int $teamId, string $name, ?string $code, array $extra = []): Enterprise
    {
        return Enterprise::withoutGlobalScopes()->create(array_merge([
            'team_id' => $teamId,
            'name' => $name,
            'code' => $code,
            'type_id' => 1,
            'status_id' => 1,
        ], $extra));
    }
}
