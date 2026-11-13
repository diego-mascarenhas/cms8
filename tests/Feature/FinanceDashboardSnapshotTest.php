<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\TeamSnapshot;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\Finance\FinanceDashboardSnapshotService;
use App\Services\TokenUsageLogService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceDashboardSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CurrencySeeder::class]);
    }

    public function test_capture_stores_finance_dashboard_snapshot_and_api_returns_it(): void
    {
        $user = $this->makeAdminUser();
        $team = $user->currentTeam;
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-main',
            'name' => 'Main',
            'currency_id' => 978,
            'status' => 1,
        ]);
        $paymentType = PaymentType::query()->create(['name' => 'Transfer']);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $paymentType->id,
            'amount' => 500,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $snapshot = app(FinanceDashboardSnapshotService::class)->capture($team, (int) now()->year);

        $this->assertSame(TeamSnapshot::TYPE_FINANCE_DASHBOARD, $snapshot->type);
        $this->assertSame('year:'.now()->year, $snapshot->key);
        $this->assertSame(500.0, (float) data_get($snapshot->payload, 'cash_position.liquid_assets'));
        $this->assertIsArray(data_get($snapshot->payload, 'flow_projection.points'));
        $this->assertIsArray(data_get($snapshot->payload, 'category_charts.income_categories'));

        $token = $user->createToken('finance-snapshot')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/finance/dashboard-snapshot?year='.now()->year)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payload.cash_position.liquid_assets', 500);
    }

    public function test_cfo_usage_is_logged_against_the_team(): void
    {
        $user = $this->makeAdminUser();
        $team = $user->currentTeam;
        $service = app(FinanceCfoBriefService::class);

        $method = new ReflectionMethod(FinanceCfoBriefService::class, 'logUsage');
        $method->setAccessible(true);
        $method->invoke(
            $service,
            $team,
            ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
            str_repeat('a', 40),
            str_repeat('b', 20),
        );

        $this->assertSame(1, TokenUsageLog::withoutGlobalScopes()->where('team_id', $team->id)->count());
        $this->assertSame('FinanceCfoBriefService', TokenUsageLog::withoutGlobalScopes()->first()->service);
        $this->assertSame(150, (int) TokenUsageLog::withoutGlobalScopes()->first()->json_tokens);

        $method->invoke($service, $team, null, 'input text here', 'output text');
        $this->assertSame(2, TokenUsageLog::withoutGlobalScopes()->where('team_id', $team->id)->count());
        $this->assertGreaterThan(0, TokenUsageLogService::totalTokensFromUsage([
            'prompt_tokens' => 1,
            'completion_tokens' => 1,
        ]));
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }
}
