<?php

namespace Tests\Unit\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\Team;
use App\Models\User;
use App\Services\Finance\PaymentReportingCurrencyService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentReportingCurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentReportingCurrencyService $service;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $this->team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $this->team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user);

        $this->service = app(PaymentReportingCurrencyService::class);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => now()->toDateString(),
            'fetched_at' => now(),
        ]);
    }

    public function test_reporting_currency_uses_team_setting_when_configured(): void
    {
        $this->team->setSetting('finance_reporting_currency', 'USD', ['group' => 'finance']);

        $this->assertSame('USD', $this->service->reportingCurrencyForTeam($this->team));
    }

    public function test_sum_approved_payments_converts_multiple_currencies_to_reporting_currency(): void
    {
        $this->team->setSetting('finance_reporting_currency', 'EUR', ['group' => 'finance']);

        $usdAccount = $this->createAccount('USD', 840);
        $eurAccount = $this->createAccount('EUR', 978);
        $paymentType = PaymentType::query()->create(['name' => 'Transfer']);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $this->team->id,
            'transaction_type' => TransactionType::EXPENSE,
            'date' => now()->toDateString(),
            'account_id' => $usdAccount->id,
            'type_id' => $paymentType->id,
            'amount' => 100,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $this->team->id,
            'transaction_type' => TransactionType::EXPENSE,
            'date' => now()->toDateString(),
            'account_id' => $eurAccount->id,
            'type_id' => $paymentType->id,
            'amount' => 50,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $this->assertSame(2, Payment::withoutGlobalScopes()->count());
        $this->assertNotSame($usdAccount->id, $eurAccount->id);

        $query = Payment::withoutGlobalScope('team')
            ->where('payments.team_id', $this->team->id)
            ->where('payments.transaction_type', TransactionType::EXPENSE)
            ->where('payments.status', 2);

        $sums = $this->service->sumsByCurrency($query);

        $this->assertEqualsCanonicalizing(
            ['EUR' => 50.0, 'USD' => 100.0],
            $sums,
        );

        $total = $this->service->sumApprovedPaymentsConverted(
            TransactionType::EXPENSE,
            'EUR',
        );

        $this->assertSame('140.00', number_format($total, 2, '.', ''));
    }

    private function createAccount(string $code, int $currencyId): PaymentAccount
    {
        Currency::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $code, 'symbol' => $code, 'status' => true],
        );

        return PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $this->team->id,
            'code' => strtolower($code).'-main',
            'name' => $code.' account',
            'currency_id' => $currencyId,
            'status' => 1,
        ]);
    }
}
