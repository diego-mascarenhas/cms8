<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\TeamDefaultPaymentMethodStatus;
use App\Services\TeamStripeCustomerService;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamDefaultPaymentMethodStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    #[DataProvider('expiryCases')]
    public function test_with_expiry_flags(
        string $today,
        int $expMonth,
        int $expYear,
        bool $expired,
        bool $expiringSoon,
        ?int $daysUntilExpiry,
    ): void {
        Carbon::setTestNow(Carbon::parse($today, 'UTC'));

        $service = new TeamDefaultPaymentMethodStatus(
            Mockery::mock(TeamStripeCustomerService::class),
        );

        $result = $service->withExpiryFlags([
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
        ], Carbon::parse($today, 'UTC'));

        $this->assertSame($expired, $result['expired']);
        $this->assertSame($expiringSoon, $result['expiring_soon']);
        $this->assertSame($daysUntilExpiry, $result['days_until_expiry']);
        $this->assertSame('visa', $result['brand']);
        $this->assertSame('4242', $result['last4']);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int, 3: bool, 4: bool, 5: int|null}>
     */
    public static function expiryCases(): array
    {
        return [
            'expired last month' => ['2026-11-17', 10, 2026, true, false, null],
            'expires today end of month' => ['2026-11-30', 11, 2026, false, true, 0],
            'expires in 15 days' => ['2026-11-15', 11, 2026, false, true, 15],
            'expires in 16 days' => ['2026-11-14', 11, 2026, false, false, 16],
            'far future' => ['2026-11-17', 8, 2028, false, false, 653],
        ];
    }
}
