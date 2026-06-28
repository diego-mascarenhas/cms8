<?php

namespace Tests\Feature;

use App\Models\PaymentAccount;
use App\Models\Team;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PaymentAccountSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            PaymentTypeSeeder::class,
        ]);
    }

    public function test_seeder_creates_accounts_with_expected_payment_types(): void
    {
        $team = Team::factory()->create();

        $this->seed(PaymentAccountSeeder::class);

        $bankEur = $this->accountForTeam($team, 'BANK_EUR');
        $paypalEur = $this->accountForTeam($team, 'PAYPAL_EUR');
        $stripeEur = $this->accountForTeam($team, 'STRIPE_EUR');
        $mercadoPagoArs = $this->accountForTeam($team, 'MPAGO_ARS');

        $this->assertSame([2, 11], $this->paymentTypeIds($bankEur));
        $this->assertSame([6, 7], $this->paymentTypeIds($paypalEur));
        $this->assertSame([6, 8], $this->paymentTypeIds($stripeEur));
        $this->assertSame(32, (int) $mercadoPagoArs->currency_id);
        $this->assertSame([2, 12], $this->paymentTypeIds($mercadoPagoArs));
        $this->assertSame(11, PaymentAccount::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_seeder_updates_payment_types_on_rerun(): void
    {
        $team = Team::factory()->create();

        $this->seed(PaymentAccountSeeder::class);

        $account = $this->accountForTeam($team, 'PAYPAL_USD');
        $account->paymentTypes()->sync([1]);

        $this->seed(PaymentAccountSeeder::class);

        $this->assertSame([6, 7], $this->paymentTypeIds($account->fresh()));
    }

    private function accountForTeam(Team $team, string $code): PaymentAccount
    {
        $account = PaymentAccount::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('code', $code)
            ->first();

        $this->assertNotNull($account);

        return $account;
    }

    /**
     * @return list<int>
     */
    private function paymentTypeIds(PaymentAccount $account): array
    {
        return $account->paymentTypes()
            ->pluck('payment_types.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
    }
}
