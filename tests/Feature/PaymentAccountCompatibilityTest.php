<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
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

    public function test_resolve_defaults_prefers_last_expense_payment_account(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $eur = $this->createAccount('EUR_BANK', $teamId, 978);
        $usd = $this->createAccount('USD_BANK', $teamId, 840);
        $this->actingAs($user);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::EXPENSE,
            'date' => '2026-11-01',
            'account_id' => $usd->id,
            'type_id' => 2,
            'amount' => 10,
            'status' => 2,
        ]);

        $accounts = PaymentAccount::query()->whereIn('id', [$eur->id, $usd->id])->orderBy('name')->get();
        $types = PaymentType::query()->orderBy('name')->get();
        [$accountId, $typeId, $source] = app(PaymentAccountCompatibilityService::class)->resolveDefaults(
            $accounts,
            $types,
            2,
            (int) $usd->id,
            978,
        );

        $this->assertSame((int) $usd->id, $accountId);
        $this->assertSame('last_payment', $source);
        $this->assertNotNull($typeId);
    }

    public function test_resolve_defaults_falls_back_to_invoice_currency_when_no_last_payment(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $usd = $this->createAccount('USD_ONLY', $teamId, 840);
        $eur = $this->createAccount('EUR_ONLY', $teamId, 978);
        $this->actingAs($user);

        $accounts = PaymentAccount::query()->whereIn('id', [$usd->id, $eur->id])->orderBy('name')->get();
        $types = PaymentType::query()->orderBy('name')->get();
        [$accountId, , $source] = app(PaymentAccountCompatibilityService::class)->resolveDefaults(
            $accounts,
            $types,
            2,
            null,
            978,
        );

        $this->assertSame((int) $eur->id, $accountId);
        $this->assertSame('currency', $source);
    }

    public function test_expense_form_api_defaults_to_last_expense_payment_account(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $this->createAccount('EUR_FORM', $teamId, 978);
        $usd = $this->createAccount('USD_FORM', $teamId, 840);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::EXPENSE,
            'date' => '2026-11-10',
            'account_id' => $usd->id,
            'type_id' => 2,
            'amount' => 25,
            'status' => 2,
        ]);

        $token = $user->createToken('expense-defaults')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/form')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('default_payment_account_id', $usd->id)
            ->assertJsonPath('default_payment_account_source', 'last_payment');
    }

    public function test_expense_form_api_defaults_to_invoice_currency_when_no_expense_payment(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $this->createAccount('USD_CUR', $teamId, 840);
        $eur = $this->createAccount('EUR_CUR', $teamId, 978);

        $token = $user->createToken('expense-currency-default')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/expenses/form')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('default_payment_account_id', $eur->id)
            ->assertJsonPath('default_payment_account_source', 'currency');
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
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function createAccount(string $code, ?int $teamId = null, int $currencyId = 978): PaymentAccount
    {
        $teamId ??= (int) (Team::query()->first()?->id ?? Team::factory()->create()->id);

        return PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => $code,
            'name' => 'Account '.$code,
            'symbol' => $currencyId === 840 ? '$' : '€',
            'currency_id' => $currencyId,
            'status' => 1,
        ]);
    }
}
