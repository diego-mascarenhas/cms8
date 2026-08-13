<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Module;
use App\Models\PaymentSync;
use App\Models\Team;
use App\Models\User;
use App\Services\Finance\InvoiceElectronicPaymentLinkService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoElectronicPaymentLinkSeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoElectronicPaymentLinkSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
            PaymentTypeSeeder::class,
        ]);

        foreach (['invoices', 'payments'] as $key)
        {
            Module::firstOrCreate(['key' => $key], ['name' => ucfirst($key), 'is_core' => false]);
        }
    }

    public function test_seeder_creates_unpaid_invoice_and_mercadopago_syncs_newest_first(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->create([
            'name' => "REVISION ALPHA's Team",
            'personal_team' => false,
        ]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->seed(DemoElectronicPaymentLinkSeeder::class);

        $invoice = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('number', DemoElectronicPaymentLinkSeeder::INVOICE_NUMBER)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(32, (int) $invoice->currency_id);
        $this->assertGreaterThan(0, (float) $invoice->balance);
        $this->assertSame(2, (int) $invoice->status);

        $syncs = PaymentSync::query()
            ->where('team_id', $team->id)
            ->where('provider', 'mercadopago')
            ->get();

        $this->assertCount(8, $syncs);

        $orderedIds = app(InvoiceElectronicPaymentLinkService::class)
            ->availableSyncs($invoice)
            ->pluck('external_id')
            ->all();

        $this->assertSame('demo-mp-'.$team->id.'-3', $orderedIds[0]);
        $this->assertSame('demo-mp-'.$team->id.'-7', end($orderedIds));

        $this->actingAs($user)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee(__('invoice_payment.electronic_title'), false)
            ->assertSee('form-control select2', false)
            ->assertSeeInOrder(['10/08/2026', '07/08/2026', '16/07/2026'], false);
    }

    public function test_seeder_skips_when_target_teams_are_missing(): void
    {
        Team::factory()->create(['name' => 'Other Team']);

        $this->seed(DemoElectronicPaymentLinkSeeder::class);

        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
        $this->assertSame(0, PaymentSync::query()->count());
    }
}
