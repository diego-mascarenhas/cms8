<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\Billing\StripeInvoiceOutOfBandPaymentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class InvoicePaymentRegistrationService
{
    /** @var list<int> */
    private const BLOCKED_STATUSES = [3, 4, 5, 6, 7, 9];

    private const CASH_PAYMENT_TYPE_ID = 1;

    public function __construct(
        private readonly InvoiceCurrencyService $invoiceCurrencyService,
        private readonly StripeInvoiceOutOfBandPaymentService $stripeOutOfBandPaymentService,
    ) {}

    public function canRegisterPayment(User $user, Invoice $invoice): bool
    {
        if (! $user->currentTeam || (int) $invoice->team_id !== (int) $user->currentTeam->id)
        {
            return false;
        }

        if (! $user->ownsTeam($user->currentTeam))
        {
            return false;
        }

        return $this->isPaymentRegistrationEligible($invoice);
    }

    public function isPaymentRegistrationEligible(Invoice $invoice): bool
    {
        if ((float) $invoice->balance <= 0)
        {
            return false;
        }

        if (in_array((int) $invoice->status, self::BLOCKED_STATUSES, true))
        {
            return false;
        }

        if ($this->isStripeInvoiceCollected($invoice))
        {
            return false;
        }

        return $this->cashAccountsForInvoiceCurrency($invoice)->isNotEmpty();
    }

    public function isStripeInvoiceCollected(Invoice $invoice): bool
    {
        if (! filled($invoice->source_reference_id) || ! str_starts_with((string) $invoice->source_reference_id, 'in_'))
        {
            return false;
        }

        $sync = $invoice->relationLoaded('stripeInvoiceSync')
            ? $invoice->stripeInvoiceSync
            : $invoice->stripeInvoiceSync()->first();

        if (! $sync instanceof InvoiceSync)
        {
            return false;
        }

        if ($sync->paid)
        {
            return true;
        }

        return strtolower((string) $sync->status) === 'paid';
    }

    /**
     * @return array{
     *     amount: float,
     *     date: string,
     *     account_id: int|null,
     *     type_id: int,
     *     accounts: array<int, array{id: int, name: string}>,
     *     payment_types: array<int, array{id: int, name: string}>,
     *     currency_code: string
     * }
     */
    public function formDefaults(Invoice $invoice): array
    {
        $accounts = $this->cashAccountsForInvoiceCurrency($invoice);
        $preferredAccount = $accounts->first(function (PaymentAccount $account): bool
        {
            $name = mb_strtolower((string) $account->name);

            return str_contains($name, 'efectivo') || preg_match('/\bcash\b/u', $name) === 1;
        }) ?? $accounts->first();

        $paymentTypes = PaymentType::query()
            ->whereKey(self::CASH_PAYMENT_TYPE_ID)
            ->get(['id', 'name'])
            ->map(fn ($type) => ['id' => $type->id, 'name' => $type->display_name])
            ->values()
            ->all();

        return [
            'amount' => round((float) $invoice->balance, 2),
            'date' => now()->toDateString(),
            'account_id' => $preferredAccount?->id,
            'type_id' => self::CASH_PAYMENT_TYPE_ID,
            'accounts' => $accounts
                ->map(fn (PaymentAccount $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'payment_type_ids' => [self::CASH_PAYMENT_TYPE_ID],
                ])
                ->values()
                ->all(),
            'payment_types' => $paymentTypes,
            'currency_code' => $invoice->currency_code,
        ];
    }

    /**
     * @return Collection<int, PaymentAccount>
     */
    public function accountsForInvoiceCurrency(Invoice $invoice): Collection
    {
        $currencyId = $invoice->currency_id ?? $this->invoiceCurrencyService->defaultCurrencyId();

        return PaymentAccount::withoutGlobalScopes()
            ->where('team_id', $invoice->team_id)
            ->where('status', 1)
            ->where('currency_id', $currencyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Manual invoice payments are cash-only; exclude bank/e-wallet accounts.
     *
     * @return Collection<int, PaymentAccount>
     */
    public function cashAccountsForInvoiceCurrency(Invoice $invoice): Collection
    {
        return $this->accountsForInvoiceCurrency($invoice)
            ->filter(fn (PaymentAccount $account): bool => $this->isCashAccountForManualPayment($account))
            ->values();
    }

    public function isCashAccountForManualPayment(PaymentAccount $account): bool
    {
        $account->loadMissing('paymentTypes');

        if ($account->paymentTypes->isNotEmpty())
        {
            return $account->paymentTypes->contains(
                fn ($type): bool => (int) $type->id === self::CASH_PAYMENT_TYPE_ID,
            );
        }

        $code = mb_strtoupper((string) $account->code);
        $name = mb_strtolower((string) $account->name);

        if ($code === 'CASH' || str_contains($code, 'CASH'))
        {
            return true;
        }

        if (str_contains($name, 'efectivo') || preg_match('/\bcash\b/u', $name) === 1)
        {
            return true;
        }

        // "Caja Fuerte" / generic cash drawer, but not "caja de ahorro(s)".
        return str_contains($name, 'caja') && ! str_contains($name, 'ahorro');
    }

    /**
     * @param  array{amount: float, date: string, account_id: int, type_id: int, remarks?: string|null}  $data
     */
    public function register(User $user, Invoice $invoice, array $data): Payment
    {
        if (! $this->canRegisterPayment($user, $invoice))
        {
            throw ValidationException::withMessages([
                'amount' => __('invoice_payment.errors.not_allowed'),
            ]);
        }

        $amount = round((float) $data['amount'], 2);
        $balance = round((float) $invoice->balance, 2);

        if ($amount <= 0)
        {
            throw ValidationException::withMessages([
                'amount' => __('invoice_payment.errors.amount_invalid'),
            ]);
        }

        if ($amount > $balance)
        {
            throw ValidationException::withMessages([
                'amount' => __('invoice_payment.errors.amount_exceeds_balance'),
            ]);
        }

        $account = PaymentAccount::withoutGlobalScopes()
            ->where('team_id', $invoice->team_id)
            ->where('status', 1)
            ->whereKey($data['account_id'])
            ->first();

        if (! $account instanceof PaymentAccount)
        {
            throw ValidationException::withMessages([
                'account_id' => __('invoice_payment.errors.account_invalid'),
            ]);
        }

        $expectedCurrencyId = $invoice->currency_id ?? $this->invoiceCurrencyService->defaultCurrencyId();
        if ((int) $account->currency_id !== (int) $expectedCurrencyId)
        {
            throw ValidationException::withMessages([
                'account_id' => __('invoice_payment.errors.account_currency_mismatch'),
            ]);
        }

        if (! $this->isCashAccountForManualPayment($account))
        {
            throw ValidationException::withMessages([
                'account_id' => __('invoice_payment.errors.account_invalid'),
            ]);
        }

        if ((int) $data['type_id'] !== self::CASH_PAYMENT_TYPE_ID)
        {
            throw ValidationException::withMessages([
                'type_id' => __('invoice_payment.errors.type_not_allowed_for_account'),
            ]);
        }

        $transactionType = $invoice->operation === 'buy'
            ? TransactionType::EXPENSE
            : TransactionType::INCOME;

        return Payment::query()->getModel()->getConnection()->transaction(function () use ($invoice, $data, $amount, $account, $transactionType): Payment
        {
            $payment = Payment::withoutGlobalScopes()->create([
                'team_id' => $invoice->team_id,
                'enterprise_id' => $invoice->enterprise_id,
                'transaction_type' => $transactionType,
                'date' => $data['date'],
                'invoice_id' => $invoice->id,
                'account_id' => $account->id,
                'type_id' => (int) $data['type_id'],
                'amount' => $amount,
                'remarks' => filled($data['remarks'] ?? null) ? $data['remarks'] : null,
                'status' => 2,
            ]);

            $invoice->balance = max(0, round((float) $invoice->balance - $amount, 2));
            if ((float) $invoice->balance <= 0)
            {
                $invoice->status = 2;
            }
            $invoice->save();

            $this->stripeOutOfBandPaymentService->markPaidFromPayment($payment);

            return $payment->fresh() ?? $payment;
        });
    }
}
