<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Models\User;
use App\Services\Finance\PaymentAccountCompatibilityService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAccountCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            PaymentTypeSeeder::class,
        ]);
    }

    public function test_account_without_configuration_accepts_all_active_payment_types(): void
    {
        $account = $this->createAccount('OPEN');

        $acceptedIds = app(PaymentAccountCompatibilityService::class)->acceptedPaymentTypeIds($account);

        $this->assertGreaterThan(1, count($acceptedIds));
        $this->assertContains(2, $acceptedIds);
    }

    public function test_configured_account_only_accepts_selected_payment_types(): void
    {
        $account = $this->createAccount('CASHBOX');
        $service = app(PaymentAccountCompatibilityService::class);

        $service->syncConfiguredPaymentTypes($account, [1, 2]);

        $this->assertTrue($service->accountAcceptsType($account, 1));
        $this->assertTrue($service->accountAcceptsType($account, 2));
        $this->assertFalse($service->accountAcceptsType($account, 6));
    }

    public function test_store_expense_rejects_incompatible_account_and_payment_type(): void
    {
        $user = $this->makeAdminUser();
        $account = $this->createAccount('CASH_ONLY', (int) $user->current_team_id);
        app(PaymentAccountCompatibilityService::class)->syncConfiguredPaymentTypes($account, [1]);
        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Proveedor test',
            'email' => 'prov@test.test',
        ]);

        $this->actingAs($user)
            ->post(route('expense.store'), [
                'document_type' => 'invoice',
                'enterprise_id' => $supplier->id,
                'date' => '2026-06-22',
                'lines' => [[
                    'concept' => 'Item',
                    'base_amount' => '100.00',
                    'vat_percent' => '0',
                    'retention_percent' => '0',
                    'allocation_percent' => '100',
                ]],
                'payments' => [[
                    'payment_date' => '2026-06-22',
                    'amount' => '100.00',
                    'type_id' => 6,
                    'account_id' => $account->id,
                    'status' => 2,
                ]],
                'submit_action' => 'save',
            ])
            ->assertSessionHasErrors([
                'payments.0.type_id' => 'La forma de pago seleccionada no está permitida para la cuenta elegida.',
            ]);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function createAccount(string $code, ?int $teamId = null): PaymentAccount
    {
        $teamId ??= (int) (Team::query()->first()?->id ?? Team::factory()->create()->id);

        return PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => $code,
            'name' => 'Account '.$code,
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);
    }
}
