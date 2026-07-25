<?php

namespace Tests\Unit;

use App\Enums\TransactionType;
use App\Models\Module;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\Team;
use App\Models\User;
use App\Services\DailyTeamDigestMetricsCollector;
use App\Services\Finance\PaymentSummaryService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
        ]);
    }

    private function createPaymentForTeam(Team $team, int $status, float $amount = 100): Payment
    {
        $account = PaymentAccount::withoutGlobalScopes()->firstOrCreate(
            ['team_id' => $team->id, 'code' => 'bank'],
            [
                'name' => 'Bank',
                'symbol' => '€',
                'currency_id' => 978,
                'status' => 1,
            ],
        );

        $type = PaymentType::query()->firstOrCreate(['name' => 'Transfer']);

        return Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $type->id,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    public function test_summary_counts_only_actionable_payments_for_pending_claim(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $this->createPaymentForTeam($team, 1, 100);
        $this->createPaymentForTeam($team, 3, 50);
        $this->createPaymentForTeam($team, 2, 200);
        $this->createPaymentForTeam($team, 9, 300);
        $this->createPaymentForTeam($team, 4, 75);
        $this->createPaymentForTeam($team, 0, 999);

        $summary = app(PaymentSummaryService::class)->forTeam($team);

        $this->assertSame(5, $summary['total_count']);
        $this->assertSame(1, $summary['in_process_count']);
        $this->assertSame(1, $summary['pending_count']);
        $this->assertSame(2, $summary['actionable_count']);
        $this->assertSame(2, $summary['pending_claim_count']);
        $this->assertSame(1, $summary['approved_count']);
        $this->assertSame(2, $summary['failed_count']);
        $this->assertSame(100.0, $summary['in_process_amount']);
        $this->assertSame(50.0, $summary['pending_amount']);
        $this->assertSame(200.0, $summary['approved_amount']);
        $this->assertSame(375.0, $summary['failed_amount']);
    }

    public function test_apply_status_filter_limits_query_by_status_group(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $this->createPaymentForTeam($team, 1);
        $this->createPaymentForTeam($team, 3);
        $this->createPaymentForTeam($team, 2);
        $this->createPaymentForTeam($team, 9);
        $this->createPaymentForTeam($team, 4);

        $service = app(PaymentSummaryService::class);
        $baseQuery = fn () => Payment::withoutGlobalScopes()->where('team_id', $team->id);

        $this->assertSame(1, $service->applyStatusFilter($baseQuery(), '1')->count());
        $this->assertSame(1, $service->applyStatusFilter($baseQuery(), '3')->count());
        $this->assertSame(2, $service->applyStatusFilter($baseQuery(), 'actionable')->count());
        $this->assertSame(1, $service->applyStatusFilter($baseQuery(), '2')->count());
        $this->assertSame(2, $service->applyStatusFilter($baseQuery(), 'failed')->count());
        $this->assertSame(5, $service->applyStatusFilter($baseQuery(), 'all')->count());
    }

    public function test_digest_payment_metrics_use_actionable_statuses_only(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        Module::firstOrCreate(['key' => 'payments'], ['name' => 'Payments', 'is_core' => false]);
        $team->enableModule('payments');

        $this->createPaymentForTeam($team, 1);
        $this->createPaymentForTeam($team, 3);
        $this->createPaymentForTeam($team, 9);
        $this->createPaymentForTeam($team, 4);

        $digest = app(DailyTeamDigestMetricsCollector::class)->collect($user, $team);

        $this->assertSame(2, $digest['payments']['pending_claim_count'] ?? null);
    }
}
