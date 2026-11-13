<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\BankStatement;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\PaymentAccount;
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
        return $this->sumPaymentsConverted(
            $transactionType,
            $targetCurrency,
            $scope,
            approvedOnly: true,
        );
    }

    /**
     * Convert payments to a single reporting currency.
     * Payments without a resolvable account/invoice currency are skipped (never assumed EUR).
     */
    public function sumPaymentsConverted(
        TransactionType $transactionType,
        string $targetCurrency,
        ?Closure $scope = null,
        bool $approvedOnly = false,
    ): float {
        $targetCurrency = strtoupper(trim($targetCurrency));
        $query = $this->scopedPaymentQuery()
            ->where('payments.transaction_type', $transactionType);

        if ($approvedOnly)
        {
            $query->where('payments.status', 2);
        }

        if ($scope instanceof Closure)
        {
            $scope($query);
        }

        $payments = $this->loadPaymentsWithAccountCurrency($query, [
            'payments.id',
            'payments.amount',
            'payments.date',
            'payments.account_id',
            'payments.invoice_id',
        ]);

        $total = 0.0;

        foreach ($payments as $payment)
        {
            $currencyCode = $this->resolvedPaymentCurrencyCode($payment);

            if ($currencyCode === null)
            {
                continue;
            }

            $amount = (float) $payment->amount;

            if ($currencyCode === $targetCurrency)
            {
                $total += $amount;

                continue;
            }

            $conversionDate = $payment->date instanceof Carbon
                ? $payment->date->copy()->endOfDay()
                : now();

            $converted = ExchangeRate::convertOnOrBeforeDate(
                $amount,
                $currencyCode,
                $targetCurrency,
                $conversionDate,
            );

            if ($converted === null)
            {
                $converted = ExchangeRate::convert($amount, $currencyCode, $targetCurrency);
            }

            if ($converted === null)
            {
                continue;
            }

            $total += $converted;
        }

        return round($total, 2);
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
        return $this->accountBalancesWithBank()->map(function (array $account): array
        {
            return [
                'id' => $account['id'],
                'name' => $account['name'],
                'balance' => $account['balance'],
                'currency_code' => $account['currency_code'],
            ];
        });
    }

    /**
     * Book balances from payments, enriched with the latest extract stated balance when present.
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     balance: float,
     *     book_balance: float,
     *     bank_balance: float|null,
     *     currency_code: string,
     *     source: 'bank'|'book',
     *     statement_period: string|null,
     *     statement_balanced: bool|null,
     *     statement_difference: float|null,
     *     statement_matched: int|null,
     *     statement_only: int|null,
     *     payment_only: int|null
     * }>
     */
    public function accountBalancesWithBank(Team|int|null $team = null): Collection
    {
        $teamId = $this->resolveTeamId($team);
        $incomeType = TransactionType::INCOME->value;

        $balanceByAccountId = $this->scopedPaymentQuery($teamId)
            ->whereNotNull('payments.account_id')
            ->groupBy('payments.account_id')
            ->selectRaw(
                'payments.account_id as account_id, COALESCE(SUM(CASE WHEN payments.transaction_type = ? THEN payments.amount ELSE -payments.amount END), 0) as balance',
                [$incomeType],
            )
            ->pluck('balance', 'account_id');

        $accountsQuery = PaymentAccount::withoutGlobalScopes()->with('currency')->orderBy('name');

        if ($teamId)
        {
            $accountsQuery->where('team_id', $teamId);
        }

        $accounts = $accountsQuery->get();

        $latestStatements = $this->latestStatementsByAccountId(
            $accounts->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $teamId,
        );

        return $accounts->map(function (PaymentAccount $account) use ($balanceByAccountId, $latestStatements): array
        {
            $bookBalance = (float) ($balanceByAccountId[$account->id] ?? 0);
            $statement = $latestStatements[(int) $account->id] ?? null;
            $summary = is_array($statement?->validation_summary) ? $statement->validation_summary : [];
            $bankBalance = array_key_exists('stated_balance', $summary) && $summary['stated_balance'] !== null
                ? round((float) $summary['stated_balance'], 2)
                : null;
            $usesBank = $bankBalance !== null;

            return [
                'id' => (int) $account->id,
                'name' => (string) $account->name,
                'balance' => $usesBank ? $bankBalance : $bookBalance,
                'book_balance' => round($bookBalance, 2),
                'bank_balance' => $bankBalance,
                'currency_code' => strtoupper((string) ($account->currency->code ?? 'USD')),
                'source' => $usesBank ? 'bank' : 'book',
                'statement_period' => $statement instanceof BankStatement ? $statement->periodLabel() : null,
                'statement_balanced' => array_key_exists('balanced', $summary) ? (bool) $summary['balanced'] : null,
                'statement_difference' => array_key_exists('difference', $summary) && $summary['difference'] !== null
                    ? round((float) $summary['difference'], 2)
                    : null,
                'statement_matched' => array_key_exists('matched', $summary) ? (int) $summary['matched'] : null,
                'statement_only' => array_key_exists('statement_only', $summary) ? (int) $summary['statement_only'] : null,
                'payment_only' => array_key_exists('payment_only', $summary) ? (int) $summary['payment_only'] : null,
            ];
        });
    }

    /**
     * @param  list<int>  $accountIds
     * @return array<int, BankStatement>
     */
    private function latestStatementsByAccountId(array $accountIds, ?int $teamId = null): array
    {
        if ($accountIds === [])
        {
            return [];
        }

        $resolvedTeamId = $teamId ?? auth()->user()?->currentTeam?->id;

        $query = BankStatement::query()
            ->whereIn('payment_account_id', $accountIds)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('id');

        if ($resolvedTeamId)
        {
            $query->where('team_id', $resolvedTeamId);
        }

        $latest = [];

        foreach ($query->get() as $statement)
        {
            $accountId = (int) $statement->payment_account_id;

            if (! isset($latest[$accountId]))
            {
                $latest[$accountId] = $statement;
            }
        }

        return $latest;
    }

    private function scopedPaymentQuery(?int $teamId = null): Builder
    {
        $query = Payment::query()->withoutGlobalScope('team');
        $resolvedTeamId = $teamId ?? auth()->user()?->currentTeam?->id;

        if ($resolvedTeamId)
        {
            $query->where('payments.team_id', $resolvedTeamId);
        }

        return $query;
    }

    private function resolveTeamId(Team|int|null $team): ?int
    {
        if ($team instanceof Team)
        {
            return (int) $team->id;
        }

        if (is_int($team) && $team > 0)
        {
            return $team;
        }

        $authTeamId = auth()->user()?->currentTeam?->id;

        return $authTeamId ? (int) $authTeamId : null;
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
        return $this->resolvedPaymentCurrencyCode($payment)
            ?? (strtoupper(trim((string) config('verifactu.default_currency', 'EUR'))) ?: null);
    }

    /**
     * Currency from account or invoice only. No default-EUR guess for unknown accounts.
     */
    private function resolvedPaymentCurrencyCode(Payment $payment): ?string
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

        return null;
    }
}
