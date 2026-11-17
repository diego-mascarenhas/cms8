<?php

namespace App\Services\Billing;

use App\Models\Team;
use App\Services\StripeAccountResolver;
use App\Services\TeamStripeCustomerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Throwable;

class TeamDefaultPaymentMethodStatus
{
    public const EXPIRING_SOON_DAYS = 15;

    public function __construct(
        private readonly TeamStripeCustomerService $customerService,
    ) {}

    /**
     * Default card on the team's platform Stripe customer, with expiry flags.
     *
     * @return array{
     *     brand: string,
     *     last4: string,
     *     exp_month: int,
     *     exp_year: int,
     *     expired: bool,
     *     expiring_soon: bool,
     *     days_until_expiry: int|null
     * }|null
     */
    public function forTeam(Team $team): ?array
    {
        $customerId = trim((string) ($this->customerService->getStripeCustomerIdForCategory($team, '') ?? ''));
        if ($customerId === '' || ! str_starts_with($customerId, 'cus_'))
        {
            return null;
        }

        $secret = trim(StripeAccountResolver::secretForCategory(''));
        if ($secret === '')
        {
            return null;
        }

        $cacheKey = $this->cacheKey($team->id, $customerId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['card']) && is_array($cached['card']))
        {
            return $this->withExpiryFlags($cached['card']);
        }

        try
        {
            $card = $this->fetchDefaultCard($secret, $customerId);
        } catch (Throwable $e)
        {
            Log::warning('Could not load team default payment method status', [
                'team_id' => $team->id,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        Cache::put($cacheKey, ['card' => $card], now()->addMinutes(10));

        return $card === null ? null : $this->withExpiryFlags($card);
    }

    /**
     * @param  array{brand: string, last4: string, exp_month: int, exp_year: int}  $card
     * @return array{
     *     brand: string,
     *     last4: string,
     *     exp_month: int,
     *     exp_year: int,
     *     expired: bool,
     *     expiring_soon: bool,
     *     days_until_expiry: int|null
     * }
     */
    public function withExpiryFlags(array $card, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->startOfDay();
        $expMonth = (int) ($card['exp_month'] ?? 0);
        $expYear = (int) ($card['exp_year'] ?? 0);

        if ($expMonth < 1 || $expMonth > 12 || $expYear < 2000)
        {
            return [
                'brand' => (string) ($card['brand'] ?? ''),
                'last4' => (string) ($card['last4'] ?? ''),
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'expired' => false,
                'expiring_soon' => false,
                'days_until_expiry' => null,
            ];
        }

        $expiresAt = Carbon::create($expYear, $expMonth, 1, 0, 0, 0, $today->timezoneName)
            ->endOfMonth()
            ->startOfDay();

        $daysUntilExpiry = (int) $today->diffInDays($expiresAt, false);
        $expired = $daysUntilExpiry < 0;
        $expiringSoon = ! $expired && $daysUntilExpiry <= self::EXPIRING_SOON_DAYS;

        return [
            'brand' => (string) ($card['brand'] ?? ''),
            'last4' => (string) ($card['last4'] ?? ''),
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'days_until_expiry' => $expired ? null : max(0, $daysUntilExpiry),
        ];
    }

    /**
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    private function fetchDefaultCard(string $secret, string $customerId): ?array
    {
        $stripe = new StripeClient($secret);
        $customer = $stripe->customers->retrieve($customerId, [
            'expand' => ['invoice_settings.default_payment_method'],
        ]);

        $default = $customer->invoice_settings->default_payment_method ?? null;
        if (is_object($default))
        {
            $mapped = $this->mapCardObject($default);
            if ($mapped !== null)
            {
                return $mapped;
            }
        }

        $methods = $stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
            'limit' => 1,
        ]);

        $first = $methods->data[0] ?? null;
        if (is_object($first))
        {
            return $this->mapCardObject($first);
        }

        return null;
    }

    /**
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    private function mapCardObject(object $method): ?array
    {
        $card = $method->card ?? null;
        if (! is_object($card))
        {
            return null;
        }

        $last4 = trim((string) ($card->last4 ?? ''));
        if ($last4 === '')
        {
            return null;
        }

        return [
            'brand' => trim((string) ($card->brand ?? '')),
            'last4' => $last4,
            'exp_month' => (int) ($card->exp_month ?? 0),
            'exp_year' => (int) ($card->exp_year ?? 0),
        ];
    }

    private function cacheKey(int $teamId, string $customerId): string
    {
        return 'team-default-payment-method-status:'.$teamId.':'.$customerId;
    }
}
