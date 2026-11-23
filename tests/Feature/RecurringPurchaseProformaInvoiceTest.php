<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Services\Finance\RecurringPurchaseProformaIssuer;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecurringPurchaseProformaInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_due_recurring_purchase_becomes_an_editable_draft_invoice(): void
    {
        Carbon::setTestNow('2026-11-23 09:00:00');
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor Hosting',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $category = Category::factory()->create([
            'team_id' => $team->id,
            'name' => 'Hosting',
        ]);
        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'category_id' => $category->id,
            'operation' => 'buy',
            'description' => 'Servidor mensual',
            'currency_id' => 978,
            'price' => 50,
            'discount' => 0,
            'frequency' => 1,
            'next_billing' => '2026-11-15',
            'status' => 4,
        ]);

        $issuer = app(RecurringPurchaseProformaIssuer::class);
        $draft = $issuer->issueForService($service);

        $this->assertNotNull($draft);
        $this->assertSame(9, (int) $draft->status);
        $this->assertSame('Proforma 2026-11', $draft->number);
        $this->assertSame('recurring-service-'.$service->id.'-2026-11', $draft->source_reference_id);
        $this->assertSame($category->id, (int) $draft->items()->first()->category_id);
        $this->assertSame('50.00', number_format((float) $draft->total_amount, 2, '.', ''));

        $this->assertTrue($issuer->issueForService($service->fresh())->is($draft));
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());

        $this->actingAs($user)
            ->get(route('expense.edit', $draft))
            ->assertOk()
            ->assertSee('Servidor mensual', false)
            ->assertSee('Proforma 2026-11', false);

        $this->actingAs($user)
            ->put(route('expense.update', $draft), [
                'document_type' => 'invoice',
                'enterprise_id' => $enterprise->id,
                'date' => '2026-11-15',
                'due_date' => '2026-11-15',
                'document_number' => 'FAC-PROV-44',
                'currency_id' => 978,
                'lines' => [[
                    'concept' => 'Servidor mensual',
                    'category_id' => $category->id,
                    'base_amount' => '50.00',
                    'vat_percent' => '0',
                    'retention_percent' => '0',
                    'allocation_percent' => '100',
                ]],
                'submit_action' => 'save',
            ])
            ->assertRedirect(route('expense.index'));

        $draft->refresh();
        $service->refresh();

        $this->assertSame(2, (int) $draft->status);
        $this->assertSame('FAC-PROV-44', $draft->number);
        $this->assertSame('2026-12-15', $service->next_billing->toDateString());
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());
    }

    public function test_saving_a_purchase_dated_today_opens_its_proforma(): void
    {
        Carbon::setTestNow('2026-11-23 09:00:00');
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor Hosting',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $category = Category::factory()->create([
            'team_id' => $team->id,
            'name' => 'Hosting',
        ]);

        $this->actingAs($user)
            ->get(route('service.create'))
            ->assertOk()
            ->assertSee('value="23/11/2026"', false);

        $response = $this->actingAs($user)
            ->post(route('service.store'), [
                'enterprise_id' => $enterprise->id,
                'category_id' => $category->id,
                'operation' => 'buy',
                'description' => 'Servidor de hoy',
                'currency_id' => 978,
                'price' => 80,
                'discount' => 0,
                'frequency' => 1,
                'next_billing' => '23/11/2026',
                'status' => 4,
            ]);

        $invoice = Invoice::withoutGlobalScopes()->first();
        $service = Service::withoutGlobalScopes()->first();

        $this->assertNotNull($invoice);
        $this->assertNotNull($service);
        $response->assertRedirect(route('service.show', $service));
        $this->assertSame(9, (int) $invoice->status);
        $this->assertSame('2026-11-23', Carbon::parse($invoice->date)->toDateString());
        $this->assertSame('80.00', number_format((float) $invoice->total_amount, 2, '.', ''));
        $this->assertSame('Servidor de hoy — Mensual · noviembre 2026', $invoice->items()->first()->description);
    }

    public function test_a_previous_billing_date_invoices_that_period(): void
    {
        Carbon::setTestNow('2026-11-23 09:00:00');
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Proveedor Hosting',
            'type_id' => 1,
            'status_id' => 1,
        ]);
        $category = Category::factory()->create([
            'team_id' => $team->id,
            'name' => 'Hosting',
        ]);

        $response = $this->actingAs($user)
            ->post(route('service.store'), [
                'enterprise_id' => $enterprise->id,
                'category_id' => $category->id,
                'operation' => 'buy',
                'description' => 'Servidor atrasado',
                'price' => 40,
                'frequency' => 1,
                'next_billing' => '15/10/2026',
                'status' => 4,
            ]);

        $invoice = Invoice::withoutGlobalScopes()->first();
        $service = Service::withoutGlobalScopes()->first();

        $response->assertRedirect(route('service.show', $service));
        $this->assertSame('Proforma 2026-10', $invoice->number);
        $this->assertSame('2026-10-15', Carbon::parse($invoice->date)->toDateString());
        $this->assertSame('Servidor atrasado — Mensual · octubre 2026', $invoice->items()->first()->description);
        $this->actingAs($user)
            ->get(route('service.show', $service))
            ->assertOk()
            ->assertSee('Proveedor Hosting', false)
            ->assertSee('Proforma 2026-10', false)
            ->assertSee('Crear proforma', false)
            ->assertSee('15/10/2026', false);

        $this->actingAs($user)
            ->post(route('service.proforma', $service))
            ->assertRedirect(route('service.show', $service));

        $service->refresh();
        $this->assertSame('2026-11-15', $service->next_billing->toDateString());
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());

        $this->actingAs($user)
            ->post(route('service.proforma', $service))
            ->assertRedirect(route('service.show', $service));

        $service->refresh();
        $this->assertSame('2026-12-15', $service->next_billing->toDateString());
        $this->assertSame(2, Invoice::withoutGlobalScopes()->count());
        $this->assertSame('Proforma 2026-11', Invoice::withoutGlobalScopes()->orderByDesc('id')->first()->number);
    }
}
