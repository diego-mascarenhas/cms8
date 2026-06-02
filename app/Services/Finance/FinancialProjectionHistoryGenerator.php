<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\Team;
use Carbon\Carbon;

/**
 * Generates multi-year invoiced history (sell/buy) with categorized line items for projection demos.
 */
class FinancialProjectionHistoryGenerator
{
    private const INVOICE_NUMBER_PREFIX = 'HIST-';

    /** @var list<float> Monthly seasonality index (Jan–Dec) for revenue. */
    private const SEASONALITY = [0.82, 0.88, 0.94, 1.0, 1.02, 1.05, 0.96, 0.92, 1.0, 1.08, 1.14, 1.19];

    /**
     * @param  array{
     *     income_category_ids: list<int>,
     *     expense_category_ids: list<int>,
     *     enterprise_ids: list<int>,
     *     billing_ids: array<int, int>,
     * }  $context
     */
    public function seedForTeam(Team $team, int $years = 10, bool $fresh = false): array
    {
        $years = max(1, min(15, $years));
        $teamId = (int) $team->id;

        if ($fresh)
        {
            $this->purgeHistoricalInvoices($teamId);
        }

        $paymentContext = $this->ensurePaymentContext($teamId);

        $context = $this->ensureContext($team);

        $endYear = (int) Carbon::now()->year;
        $startYear = $endYear - $years + 1;
        $invoiceTypeId = (int) (InvoiceType::query()->value('id') ?? 1);

        $incomeWeights = [0.36, 0.24, 0.18, 0.14, 0.08];
        $expenseWeights = [0.44, 0.16, 0.14, 0.16, 0.10];

        $invoiceCount = 0;
        $itemCount = 0;
        $now = now();

        for ($year = $startYear; $year <= $endYear; $year++)
        {
            $yearIndex = $year - $startYear;
            $growthFactor = pow(1.10, $yearIndex);
            $expenseRatio = max(0.55, 0.72 - ($yearIndex * 0.006));

            for ($month = 1; $month <= 12; $month++)
            {
                if ($year === $endYear && $month > (int) Carbon::now()->month)
                {
                    break;
                }

                $season = self::SEASONALITY[$month - 1];
                $monthlyRevenue = 24_000 * $growthFactor * $season * (0.92 + (mt_rand(0, 16) / 100));
                $monthlyExpense = $monthlyRevenue * $expenseRatio * (0.94 + (mt_rand(0, 12) / 100));

                $sellInvoices = mt_rand(3, 6);
                $buyInvoices = mt_rand(2, 5);

                [$invoiceCount, $itemCount] = $this->seedInvoicesForMonth(
                    $teamId,
                    $context,
                    $invoiceTypeId,
                    $year,
                    $month,
                    'sell',
                    $sellInvoices,
                    $monthlyRevenue,
                    $context['income_category_ids'],
                    $incomeWeights,
                    $invoiceCount,
                    $itemCount,
                    $now,
                );

                [$invoiceCount, $itemCount] = $this->seedInvoicesForMonth(
                    $teamId,
                    $context,
                    $invoiceTypeId,
                    $year,
                    $month,
                    'buy',
                    $buyInvoices,
                    $monthlyExpense,
                    $context['expense_category_ids'],
                    $expenseWeights,
                    $invoiceCount,
                    $itemCount,
                    $now,
                );
            }
        }

        $paymentsCreated = $this->seedPaymentsForHistoricalInvoices($teamId, $paymentContext);

        return [
            'team_id' => $teamId,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'invoices' => $invoiceCount,
            'items' => $itemCount,
            'payments' => $paymentsCreated,
        ];
    }

    public function purgeHistoricalInvoices(int $teamId): void
    {
        $invoiceIds = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('number', 'like', self::INVOICE_NUMBER_PREFIX.'%')
            ->pluck('id');

        if ($invoiceIds->isEmpty())
        {
            return;
        }

        Payment::withoutGlobalScopes()->whereIn('invoice_id', $invoiceIds)->delete();
        InvoiceItem::query()->whereIn('invoice_id', $invoiceIds)->delete();
        Invoice::withoutGlobalScopes()->whereIn('id', $invoiceIds)->delete();
    }

    /**
     * @param  array{account_id: int, type_id: int}  $paymentContext
     */
    public function seedPaymentsForHistoricalInvoices(int $teamId, array $paymentContext): int
    {
        $created = 0;
        $currentMonth = now()->startOfMonth();

        Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('number', 'like', self::INVOICE_NUMBER_PREFIX.'%')
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use ($teamId, $paymentContext, $currentMonth, &$created)
            {
                foreach ($invoices as $invoice)
                {
                    $invoiceDate = Carbon::parse($invoice->date)->startOfDay();
                    $paymentDate = $invoiceDate->copy()->addDays(mt_rand(1, 18));
                    if ($paymentDate->isFuture())
                    {
                        $paymentDate = now()->startOfDay();
                    }

                    $status = $this->resolveHistoricalPaymentStatus($invoiceDate, $currentMonth);
                    $transactionType = $invoice->operation === 'buy'
                        ? TransactionType::EXPENSE
                        : TransactionType::INCOME;

                    Payment::withoutGlobalScopes()->updateOrCreate(
                        [
                            'team_id' => $teamId,
                            'invoice_id' => $invoice->id,
                        ],
                        [
                            'enterprise_id' => $invoice->enterprise_id,
                            'transaction_type' => $transactionType,
                            'date' => $paymentDate->toDateString(),
                            'account_id' => $paymentContext['account_id'],
                            'type_id' => $paymentContext['type_id'],
                            'amount' => $invoice->total_amount,
                            'remarks' => 'HIST payment '.$invoice->number,
                            'status' => $status,
                        ],
                    );

                    $created++;
                }
            });

        $this->ensureMinimumRejectedPayments($teamId);

        return $created;
    }

    private function ensureMinimumRejectedPayments(int $teamId): void
    {
        $histInvoiceIds = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('number', 'like', self::INVOICE_NUMBER_PREFIX.'%')
            ->pluck('id');

        $baseQuery = Payment::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereIn('invoice_id', $histInvoiceIds);

        $total = (clone $baseQuery)->count();
        if ($total < 50)
        {
            return;
        }

        $rejected = (clone $baseQuery)->where('status', 4)->count();
        $target = max(1, (int) round($total * 0.015));

        if ($rejected >= $target)
        {
            return;
        }

        $toFlip = $target - $rejected;

        (clone $baseQuery)
            ->where('status', 2)
            ->inRandomOrder()
            ->limit($toFlip)
            ->get()
            ->each(function (Payment $payment)
            {
                $payment->update(['status' => 4]);
            });
    }

    private function resolveHistoricalPaymentStatus(Carbon $invoiceDate, Carbon $currentMonth): int
    {
        $roll = mt_rand(1, 10_000);
        $invoiceMonth = $invoiceDate->copy()->startOfMonth();
        $monthsAgo = (int) $currentMonth->diffInMonths($invoiceMonth);
        $isRecent = $monthsAgo <= 1;

        if ($isRecent)
        {
            if ($roll <= 3_500)
            {
                return 3;
            }

            if ($roll <= 3_700)
            {
                return 4;
            }

            return 2;
        }

        if ($roll <= 200)
        {
            return 4;
        }

        if ($roll <= 300)
        {
            return 1;
        }

        return 2;
    }

    /**
     * @return array{account_id: int, type_id: int}
     */
    private function ensurePaymentContext(int $teamId): array
    {
        $account = PaymentAccount::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        if ($account === null)
        {
            $account = PaymentAccount::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'code' => 'EUR',
                'name' => 'Cuenta demo EUR',
                'symbol' => '€',
                'currency_id' => 978,
                'status' => 1,
            ]);
        }

        $typeId = (int) (PaymentType::query()->value('id') ?? 1);

        return [
            'account_id' => (int) $account->id,
            'type_id' => $typeId,
        ];
    }

    /**
     * @return array{
     *     income_category_ids: list<int>,
     *     expense_category_ids: list<int>,
     *     enterprise_ids: list<int>,
     *     billing_ids: array<int, int>,
     * }
     */
    private function ensureContext(Team $team): array
    {
        $teamId = (int) $team->id;

        $incomeNames = [
            'Web Development & Projects',
            'Hosting & Domains',
            'IT Consulting',
            'Maintenance & Support',
            'Training & Workshops',
        ];
        $expenseNames = [
            'Payroll & Benefits',
            'Cloud & Hosting Infrastructure',
            'Software & Licenses',
            'Marketing & Sales',
            'Office & Administration',
        ];

        $incomeCategoryIds = [];
        foreach ($incomeNames as $name)
        {
            $category = Category::withoutGlobalScopes()->updateOrCreate(
                ['team_id' => $teamId, 'name' => $name],
                ['description' => 'Income category for financial projection demo', 'status' => 1],
            );
            $incomeCategoryIds[] = (int) $category->id;
        }

        $expenseCategoryIds = [];
        foreach ($expenseNames as $name)
        {
            $category = Category::withoutGlobalScopes()->updateOrCreate(
                ['team_id' => $teamId, 'name' => $name],
                ['description' => 'Expense category for financial projection demo', 'status' => 1],
            );
            $expenseCategoryIds[] = (int) $category->id;
        }

        $enterpriseIds = [];
        $billingIds = [];
        $enterpriseLabels = ['Acme Digital SL', 'Nova Hosting GmbH', 'Pine Labs SA', 'Orbit Consulting Ltd'];

        foreach ($enterpriseLabels as $index => $label)
        {
            $enterprise = Enterprise::withoutGlobalScopes()->updateOrCreate(
                ['team_id' => $teamId, 'code' => 'HIST-CLI-'.($index + 1)],
                [
                    'name' => $label,
                    'type_id' => 1,
                    'status_id' => 1,
                ],
            );

            $enterpriseIds[] = (int) $enterprise->id;

            $billing = EnterpriseBillingAddress::query()->updateOrCreate(
                ['enterprise_id' => $enterprise->id],
                [
                    'name' => $label.' Billing',
                    'identification_number' => 'HIST'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    'tax_status_type_id' => 1,
                    'address' => 'Demo Street '.($index + 1),
                    'postal_code' => '28001',
                    'locality' => 'Madrid',
                    'province' => 'Madrid',
                    'country' => 'ES',
                    'status' => 1,
                ],
            );

            $billingIds[(int) $enterprise->id] = (int) $billing->id;
        }

        return [
            'income_category_ids' => $incomeCategoryIds,
            'expense_category_ids' => $expenseCategoryIds,
            'enterprise_ids' => $enterpriseIds,
            'billing_ids' => $billingIds,
        ];
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  list<float>  $weights
     * @return array{0: int, 1: int}
     */
    private function seedInvoicesForMonth(
        int $teamId,
        array $context,
        int $invoiceTypeId,
        int $year,
        int $month,
        string $operation,
        int $invoiceCount,
        float $monthlyTotal,
        array $categoryIds,
        array $weights,
        int $totalInvoices,
        int $totalItems,
        Carbon $now,
    ): array {
        if ($monthlyTotal <= 0 || $invoiceCount < 1)
        {
            return [$totalInvoices, $totalItems];
        }

        $enterpriseIds = $context['enterprise_ids'];
        $billingIds = $context['billing_ids'];
        $remaining = $monthlyTotal;

        for ($i = 0; $i < $invoiceCount; $i++)
        {
            $isLast = $i === $invoiceCount - 1;
            $share = $isLast ? $remaining : ($monthlyTotal / $invoiceCount) * (0.75 + (mt_rand(0, 50) / 100));
            $remaining -= $share;
            if ($share <= 0)
            {
                continue;
            }

            $enterpriseId = $enterpriseIds[array_rand($enterpriseIds)];
            $day = mt_rand(1, min(28, Carbon::create($year, $month, 1)->daysInMonth));
            $date = Carbon::create($year, $month, $day)->toDateString();
            $number = sprintf(
                '%s%s-%d-%02d-%03d-%04d',
                self::INVOICE_NUMBER_PREFIX,
                $operation === 'sell' ? 'IN' : 'EX',
                $year,
                $month,
                $i + 1,
                mt_rand(1000, 9999),
            );

            $itemCountForInvoice = mt_rand(1, 3);
            $itemRows = [];
            $invoiceGross = 0.0;

            for ($j = 0; $j < $itemCountForInvoice; $j++)
            {
                $categoryIndex = $this->weightedIndex($weights);
                $lineShare = $j === $itemCountForInvoice - 1
                    ? ($share - $invoiceGross)
                    : ($share / $itemCountForInvoice) * (0.7 + (mt_rand(0, 60) / 100));
                $quantity = $operation === 'sell' ? (float) mt_rand(1, 40) : 1.0;
                $unitPrice = max(25, $lineShare / max(1, $quantity));
                $discount = mt_rand(0, 100) < 20 ? round($unitPrice * $quantity * 0.05, 2) : 0.0;
                $lineTotal = ($quantity * $unitPrice) - $discount;
                $invoiceGross += $lineTotal;

                $itemRows[] = [
                    'category_id' => $categoryIds[$categoryIndex],
                    'description' => $this->lineDescription($operation, $categoryIndex),
                    'quantity' => $quantity,
                    'unit_price' => round($unitPrice, 2),
                    'discount' => $discount,
                    'tax_percentage' => 0,
                ];
            }

            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'enterprise_id' => $enterpriseId,
                'billing_id' => $billingIds[$enterpriseId] ?? null,
                'type_id' => $invoiceTypeId,
                'operation' => $operation,
                'number' => $number,
                'date' => $date,
                'due_date' => Carbon::parse($date)->addDays(30)->toDateString(),
                'gross_amount' => round($invoiceGross, 2),
                'discount' => 0,
                'total_amount' => round($invoiceGross, 2),
                'balance' => 0,
                'status' => mt_rand(1, 100) <= 92 ? 2 : 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $totalInvoices++;

            foreach ($itemRows as $row)
            {
                InvoiceItem::query()->create(array_merge($row, [
                    'invoice_id' => $invoice->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $totalItems++;
            }
        }

        return [$totalInvoices, $totalItems];
    }

    /**
     * @param  list<float>  $weights
     */
    private function weightedIndex(array $weights): int
    {
        $roll = mt_rand(1, 1000) / 1000;
        $cumulative = 0.0;
        foreach ($weights as $index => $weight)
        {
            $cumulative += $weight;
            if ($roll <= $cumulative)
            {
                return $index;
            }
        }

        return count($weights) - 1;
    }

    private function lineDescription(string $operation, int $categoryIndex): string
    {
        $income = [
            'Sprint development — billed hours',
            'Annual hosting plan renewal',
            'Architecture & consulting day rate',
            'SLA maintenance block',
            'Team training session',
        ];
        $expense = [
            'Monthly payroll allocation',
            'AWS / cloud provider invoice',
            'SaaS subscription bundle',
            'Paid ads & campaigns',
            'Office rent & utilities share',
        ];

        $list = $operation === 'sell' ? $income : $expense;

        return $list[$categoryIndex] ?? 'Professional services';
    }
}
