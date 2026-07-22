<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\Team;
use App\Support\StripeInvoiceMetrics;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentReportingCurrencyService
{
    public const SETTING_KEY = 'finance_reporting_currency';

    public function reportingCurrencyForTeam(Team|int $team): string
    {
        $teamModel = $team instanceof Team
            ? $team
            : Team::withoutGlobalScopes()->findOrFail($team);

        $configured = strtoupper(trim((string) $teamModel->getSetting(self::SETTING_KEY, '')));

        if ($configured !== '')
        {
            return $configured;
        }

        return strtoupper((string) config('verifactu.default_currency', 'EUR'));
    }

    public function reportingCurrencyForCurrentTeam(): string
    {
        $team = auth()->user()?->currentTeam;

        return $team instanceof Team
            ? $this->reportingCurrencyForTeam($team)
            : strtoupper((string) config('verifactu.default_currency', 'EUR'));
    }

    /**
     * @return array<string, float>
     */
    public function sumsByCurrency(Builder $query): array
    {
        $payments = $this->loadPaymentsWithAccountCurrency($query, [
            'payments.id',
            'payments.amount',
            'payments.account_id',
            'payments.invoice_id',
        ]);

        $sums = [];

        foreach ($payments as $payment)
        {
            $currencyCode = $this->paymentCurrencyCode($payment);

            if ($currencyCode === null)
            {
                continue;
            }

            $sums[$currencyCode] = ($sums[$currencyCode] ?? 0.0) + (float) $payment->amount;
        }

        return $sums;
    }

    /**
     * @param  array<string, float>  $sumsByCurrency
     */
    public function sumConverted(array $sumsByCurrency, string $targetCurrency): ?float
    {
        return StripeInvoiceMetrics::sumAmountsConvertedToCurrency(
            $sumsByCurrency,
            strtoupper(trim($targetCurrency)),
        );
    }

    public function sumApprovedPaymentsConverted(
        TransactionType $transactionType,
        string $targetCurrency,
        ?Closure $scope = null,
    ): float {
        $query = $this->scopedPaymentQuery()
            ->where('payments.transaction_type', $transactionType)
            ->where('payments.status', 2);

        if ($scope instanceof Closure)
        {
            $scope($query);
        }

        $converted = $this->sumConverted(
            $this->sumsByCurrency($query),
            $targetCurrency,
        );

        return round($converted ?? 0.0, 2);
    }

    /**
     * @return array<int, array{income: float, expense: float}>
     */
    public function monthlyTotalsConverted(int $year, string $targetCurrency): array
    {
        $targetCurrency = strtoupper(trim($targetCurrency));

        $payments = $this->loadPaymentsWithAccountCurrency(
            $this->scopedPaymentQuery()
                ->where('payments.status', 2)
                ->whereYear('payments.date', $year),
            [
                'payments.id',
                'payments.amount',
                'payments.date',
                'payments.transaction_type',
                'payments.account_id',
                'payments.invoice_id',
            ],
        );

        $totals = [];

        for ($month = 1; $month <= 12; $month++)
        {
            $totals[$month] = ['income' => 0.0, 'expense' => 0.0];
        }

        foreach ($payments as $payment)
        {
            $currencyCode = $this->paymentCurrencyCode($payment);

            if ($currencyCode === null || ! $payment->date instanceof Carbon)
            {
                continue;
            }

            $month = (int) $payment->date->format('n');

            if ($month < 1 || $month > 12)
            {
                continue;
            }

            $conversionDate = Carbon::create($year, $month, 1)->endOfMonth();
            $converted = ExchangeRate::convertOnOrBeforeDate(
                (float) $payment->amount,
                $currencyCode,
                $targetCurrency,
                $conversionDate,
            );

            if ($converted === null)
            {
                continue;
            }

            if ($payment->transaction_type === TransactionType::INCOME)
            {
                $totals[$month]['income'] += $converted;
            } elseif ($payment->transaction_type === TransactionType::EXPENSE)
            {
                $totals[$month]['expense'] += $converted;
            }
        }

        foreach ($totals as $month => $values)
        {
            $totals[$month]['income'] = round($values['income'], 2);
            $totals[$month]['expense'] = round($values['expense'], 2);
        }

        return $totals;
    }

    /**
     * @return Collection<int, array{id: int, name: string, balance: float, currency_code: string}>
     */
    public function accountBalancesForDisplay(): Collection
    {
        $incomeType = TransactionType::INCOME->value;

        $balanceByAccountId = $this->scopedPaymentQuery()
            ->whereNotNull('payments.account_id')
            ->groupBy('payments.account_id')
            ->selectRaw(
                'payments.account_id as account_id, COALESCE(SUM(CASE WHEN payments.transaction_type = ? THEN payments.amount ELSE -payments.amount END), 0) as balance',
                [$incomeType],
            )
            ->pluck('balance', 'account_id');

        if ($balanceByAccountId->isEmpty())
        {
            return collect();
        }

        return \App\Models\PaymentAccount::with('currency')
            ->whereIn('id', $balanceByAccountId->keys())
            ->orderBy('name')
            ->get()
            ->map(function ($account) use ($balanceByAccountId): array
            {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => (float) ($balanceByAccountId[$account->id] ?? 0),
                    'currency_code' => strtoupper((string) ($account->currency->code ?? 'USD')),
                ];
            });
    }

    private function scopedPaymentQuery(): Builder
    {
        $query = Payment::query()->withoutGlobalScope('team');

        $teamId = auth()->user()?->currentTeam?->id;

        if ($teamId)
        {
            $query->where('payments.team_id', $teamId);
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, Payment>
     */
    private function loadPaymentsWithAccountCurrency(Builder $query, array $columns): \Illuminate\Database\Eloquent\Collection
    {
        return (clone $query)
            ->whereNotNull('payments.account_id')
            ->with([
                'account' => fn ($relation) => $relation->withoutGlobalScopes()->with('currency'),
                'invoice' => fn ($relation) => $relation->withoutGlobalScopes()->with('currency'),
            ])
            ->get($columns);
    }

    private function paymentCurrencyCode(Payment $payment): ?string
    {
        $accountCurrency = strtoupper(trim((string) ($payment->account?->currency?->code ?? '')));
        if ($accountCurrency !== '')
        {
            return $accountCurrency;
        }

        $invoiceCurrency = strtoupper(trim((string) ($payment->invoice?->currency_code ?? '')));
        if ($invoiceCurrency !== '')
        {
            return $invoiceCurrency;
        }

        $fallback = strtoupper(trim((string) config('verifactu.default_currency', 'EUR')));

        return $fallback !== '' ? $fallback : null;
    }
}
