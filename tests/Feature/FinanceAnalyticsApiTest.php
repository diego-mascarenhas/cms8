<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\BankStatement;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\CfoBriefLauncher;
use App\Services\Finance\FinanceCfoBriefService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceAnalyticsApiTest extends TestCase
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
            InvoiceTypeSeeder::class,
        ]);
    }

    public function test_cash_position_returns_liquid_receivables_payables_and_net(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $account = $this->createAccountForTeam($user);
        $paymentType = PaymentType::query()->create(['name' => 'Transfer']);
        $client = $this->createEnterpriseForTeam($user, 'Cliente Cobro', 1);
        $supplier = $this->createEnterpriseForTeam($user, 'Proveedor Pago', 2);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $paymentType->id,
            'amount' => 1000,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'FV-AR',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 200,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $supplier->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'GC-AP',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 150,
            'discount' => 0,
            'total_amount' => 150,
            'balance' => 150,
            'currency_id' => 978,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $otherUser = $this->makeAdminUser();
        $otherAccount = $this->createAccountForTeam($otherUser);
        Payment::withoutGlobalScopes()->create([
            'team_id' => (int) $otherUser->current_team_id,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $otherAccount->id,
            'type_id' => $paymentType->id,
            'amount' => 9999,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $token = $user->createToken('finance-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/finance/cash-position')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('liquid_assets', 1000)
            ->assertJsonPath('receivables.amount', 200)
            ->assertJsonPath('receivables.count', 1)
            ->assertJsonPath('payables.amount', 150)
            ->assertJsonPath('payables.count', 1)
            ->assertJsonPath('net_position', 1050)
            ->assertJsonPath('liquid_source', 'book')
            ->assertJsonPath('book_liquid_assets', 1000)
            ->assertJsonPath('bank_liquid_assets', null);
    }

    public function test_cash_position_prefers_bank_statement_stated_balance(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $account = $this->createAccountForTeam($user);
        $paymentType = PaymentType::query()->create(['name' => 'Transfer']);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $paymentType->id,
            'amount' => 1000,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        BankStatement::query()->create([
            'team_id' => $teamId,
            'payment_account_id' => $account->id,
            'provider' => BankStatement::PROVIDER_UPLOAD,
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'source' => BankStatement::SOURCE_UPLOAD,
            'original_filename' => 'extracto.csv',
            'validation_summary' => [
                'stated_balance' => 875.5,
                'balanced' => false,
                'difference' => -124.5,
                'matched' => 1,
                'statement_only' => 0,
                'payment_only' => 1,
            ],
        ]);

        $token = $user->createToken('finance-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/finance/cash-position')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('liquid_assets', 875.5)
            ->assertJsonPath('book_liquid_assets', 1000)
            ->assertJsonPath('bank_liquid_assets', 875.5)
            ->assertJsonPath('liquid_source', 'bank')
            ->assertJsonPath('accounts.0.source', 'bank')
            ->assertJsonPath('accounts.0.bank_balance', 875.5)
            ->assertJsonPath('accounts.0.book_balance', 1000);
    }

    public function test_flow_projection_returns_twelve_month_points(): void
    {
        $user = $this->makeAdminUser();
        $token = $user->createToken('finance-api')->plainTextToken;
        $year = (int) now()->year;

        $this->withToken($token)
            ->getJson('/api/finance/flow-projection?year='.$year)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('year', $year)
            ->assertJsonCount(12, 'points');
    }

    public function test_cfo_brief_starts_in_background_and_status_returns_analysis(): void
    {
        $user = $this->makeAdminUser();
        $team = $user->currentTeam;
        $year = (int) now()->year;
        $token = $user->createToken('finance-api')->plainTextToken;

        $launcher = $this->createMock(CfoBriefLauncher::class);
        $launcher->expects($this->once())->method('start')->with($team->id, $year);
        $this->app->instance(CfoBriefLauncher::class, $launcher);

        $this->withToken($token)
            ->postJson('/api/finance/cfo-brief', ['year' => $year])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('started', true)
            ->assertJsonPath('run.state', 'running');

        $this->withToken($token)
            ->getJson('/api/finance/cfo-status?year='.$year)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('run.state', 'running')
            ->assertJsonPath('analysis', null);

        $team->setSetting('finance_cfo_brief_'.$year, [
            'dafo' => [
                'fortalezas' => 'Caja sana',
                'debilidades' => '',
                'oportunidades' => '',
                'amenazas' => '',
            ],
            'fifo' => 'Cobrar vencido',
            'dagmar' => 'Subir margen',
            'actions' => ['Revisar cobros'],
            'capacity' => [
                'resources' => '',
                'hours' => '',
                'minimum_salary' => '',
                'now' => '',
                'missing_departments' => '',
            ],
            'brief' => 'Resumen CFO de prueba',
            'generated_at' => now()->toIso8601String(),
        ], [
            'type' => 'json',
            'group' => 'finance',
        ]);

        $team->setSetting('finance_cfo_run_'.$year, [
            'state' => 'done',
            'phase' => 'done',
            'started_at' => now()->toIso8601String(),
        ], [
            'type' => 'json',
            'group' => 'finance',
        ]);

        $this->withToken($token)
            ->getJson('/api/finance/cfo-status?year='.$year)
            ->assertOk()
            ->assertJsonPath('run.state', 'done')
            ->assertJsonPath('analysis.brief', 'Resumen CFO de prueba')
            ->assertJsonPath('analysis.fifo', 'Cobrar vencido')
            ->assertJsonPath('analysis.actions.0', 'Revisar cobros');

        $this->assertSame('done', app(FinanceCfoBriefService::class)->runStatus($team->fresh(), $year)['state']);
    }

    public function test_finance_endpoints_are_isolated_per_team(): void
    {
        $userA = $this->makeAdminUser();
        $userB = $this->makeAdminUser();
        $year = (int) now()->year;

        $userB->currentTeam->setSetting('finance_cfo_brief_'.$year, [
            'dafo' => [
                'fortalezas' => 'Secreto otro equipo',
                'debilidades' => '',
                'oportunidades' => '',
                'amenazas' => '',
            ],
            'fifo' => 'No filtrar',
            'dagmar' => '',
            'actions' => [],
            'capacity' => [
                'resources' => '',
                'hours' => '',
                'minimum_salary' => '',
                'now' => '',
                'missing_departments' => '',
            ],
            'brief' => 'Brief ajeno',
            'generated_at' => now()->toIso8601String(),
        ], [
            'type' => 'json',
            'group' => 'finance',
        ]);

        $tokenA = $userA->createToken('finance-api')->plainTextToken;

        $this->withToken($tokenA)
            ->getJson('/api/finance/cfo-status?year='.$year)
            ->assertOk()
            ->assertJsonPath('analysis', null);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function createEnterpriseForTeam(User $user, string $name, int $typeId): Enterprise
    {
        return Enterprise::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'type_id' => $typeId,
            'status_id' => 1,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@test.test',
        ]);
    }

    private function createAccountForTeam(User $user): PaymentAccount
    {
        return PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'bank-'.uniqid(),
            'name' => 'Main bank account',
            'currency_id' => 978,
            'status' => 1,
        ]);
    }
}
