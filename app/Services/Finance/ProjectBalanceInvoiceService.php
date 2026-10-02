<?php

namespace App\Services\Finance;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Team;
use App\Services\ProjectBudgetSpecService;
use App\Support\DatabaseSequence;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ProjectBalanceInvoiceService
{
    public function __construct(
        private readonly ProjectBudgetSpecService $budgetSpecService,
        private readonly EnterpriseVatRateResolver $vatRateResolver,
    ) {}

    /**
     * Remaining budget after an issued deposit, ready to invoice once the project is finished.
     *
     * @return array{
     *     payable_total: int,
     *     deposit_base: int,
     *     remaining_base: int,
     *     vat_percent: float,
     *     vat_applies: bool,
     *     vat_label: string,
     *     vat_amount: float,
     *     total_with_vat: float,
     *     default_description: string,
     *     stripe_customer_id: ?string,
     *     already_invoiced: bool,
     *     invoices: list<array<string, mixed>>
     * }
     */
    public function preview(Project $project): array
    {
        $project->loadMissing('client');
        $totals = $this->budgetSpecService->computeQuoteTotals($project);
        $payable = (int) $totals['payable_total'];
        $deposit = is_array(data_get($project->data, 'deposit_invoice'))
            ? data_get($project->data, 'deposit_invoice')
            : [];
        $depositInvoiced = isset($deposit['invoice_id']);
        $depositBase = $depositInvoiced
            ? (int) ($deposit['deposit_base'] ?? round($payable * ProjectDepositInvoiceService::DEPOSIT_RATIO))
            : 0;
        $remaining = max(0, $payable - $depositBase);
        $vat = $this->vatRateResolver->resolve($project->client);
        $vatAmount = round($remaining * (((float) $vat['percent']) / 100), 2);
        $stored = $this->storedInvoices($project);
        $projectName = trim((string) ($project->real_name ?: $project->name));

        return [
            'payable_total' => $payable,
            'deposit_base' => $depositBase,
            'remaining_base' => $remaining,
            'vat_percent' => (float) $vat['percent'],
            'vat_applies' => (bool) $vat['applies'],
            'vat_label' => (string) $vat['label'],
            'vat_amount' => $vatAmount,
            'total_with_vat' => round($remaining + $vatAmount, 2),
            'default_description' => __('Balance — :project', ['project' => $projectName]),
            'stripe_customer_id' => $project->client?->getStripeCustomerId(),
            'already_invoiced' => $stored !== [],
            'invoices' => $stored,
        ];
    }

    /**
     * Invoice the remaining balance in one charge, or as monthly installments from a start date.
     * A future start date stays a Stripe draft until that morning and is not charged now.
     *
     * @return array{
     *     invoices: list<Invoice>,
     *     charged: bool,
     *     scheduled: bool,
     *     hosted_invoice_url: ?string
     * }
     */
    public function issue(Project $project, string $description, Team $team, int $installments, string $startDate): array
    {
        if (! $this->projectCanInvoiceBalance($project))
        {
            throw ValidationException::withMessages([
                'project' => __('Only a finished project can invoice the remaining balance.'),
            ]);
        }

        $preview = $this->preview($project);
        if ($preview['already_invoiced'])
        {
            throw ValidationException::withMessages([
                'project' => __('The balance was already invoiced.'),
            ]);
        }

        if ($preview['remaining_base'] <= 0)
        {
            throw ValidationException::withMessages([
                'project' => __('The balance amount must be greater than zero.'),
            ]);
        }

        $enterprise = $project->client;
        if (! $enterprise instanceof Enterprise)
        {
            throw ValidationException::withMessages([
                'enterprise_id' => __('This project has no client enterprise.'),
            ]);
        }

        $stripeCustomerId = $enterprise->getStripeCustomerId();
        if (! is_string($stripeCustomerId) || $stripeCustomerId === '')
        {
            throw ValidationException::withMessages([
                'stripe_customer' => __('Link a Stripe customer on the client before invoicing the deposit.'),
            ]);
        }

        $secret = trim((string) $team->getSetting('stripe_secret'));
        if ($secret === '')
        {
            throw ValidationException::withMessages([
                'stripe_secret' => __('Configure the team Stripe secret before creating invoices.'),
            ]);
        }

        $installments = max(1, $installments);
        $description = trim($description);
        if ($description === '')
        {
            $description = $preview['default_description'];
        }

        $amounts = $this->splitAmount($preview['remaining_base'], $installments);
        $client = $this->makeStripeClient($secret);
        $canCharge = $this->customerHasPaymentMethod($client, $stripeCustomerId);
        $taxRateIds = [];
        if ($preview['vat_applies'] && $preview['vat_percent'] > 0)
        {
            $taxRateIds[] = $this->ensureExclusiveTaxRate($client, (float) $preview['vat_percent']);
        }

        $invoices = [];
        $chargedAny = false;
        $scheduledAny = false;
        $hostedUrl = null;
        $projectName = trim((string) ($project->real_name ?: $project->name));

        foreach ($amounts as $index => $amount)
        {
            $when = Carbon::parse($startDate, 'Europe/Madrid')->startOfDay()->addMonthsNoOverflow($index)->setTime(9, 0);
            $immediate = $when->lessThanOrEqualTo(now()->addHour());
            $line = $this->installmentLine($description, $projectName, $installments, $index, $amount, $when);

            $created = $this->createInstallment(
                $client,
                $project,
                $enterprise,
                $team,
                $stripeCustomerId,
                $line,
                $amount,
                $preview,
                $canCharge,
                $immediate,
                $when,
                $taxRateIds,
                $index,
                $installments,
            );

            $invoices[] = $created['invoice'];
            $chargedAny = $chargedAny || $created['charged'];
            $scheduledAny = $scheduledAny || ! $immediate;
            $hostedUrl ??= $created['hosted_invoice_url'];
        }

        return [
            'invoices' => $invoices,
            'charged' => $chargedAny,
            'scheduled' => $scheduledAny,
            'hosted_invoice_url' => $hostedUrl,
        ];
    }

    public function projectCanInvoiceBalance(Project $project): bool
    {
        if (! $project->isBudgetApproved())
        {
            return false;
        }

        return in_array((int) $project->status_id, [
            ProjectStatus::STATUS_FINISHED,
            ProjectStatus::STATUS_TO_INVOICE,
        ], true);
    }

    /**
     * @return list<int>
     */
    public function splitAmount(int $total, int $parts): array
    {
        $parts = max(1, $parts);
        $base = intdiv($total, $parts);
        $remainder = $total - ($base * $parts);
        $amounts = [];

        for ($index = 0; $index < $parts; $index++)
        {
            $amounts[] = $base + ($index === $parts - 1 ? $remainder : 0);
        }

        return $amounts;
    }

    public function installmentLine(string $description, string $projectName, int $installments, int $index, int $amount, Carbon $when): string
    {
        if ($installments <= 1)
        {
            return $description;
        }

        $lines = preg_split("/\r\n|\n|\r/", $description) ?: [];
        $lines = array_values(array_filter(array_map(trim(...), $lines), fn (string $line): bool => $line !== ''));
        if (count($lines) === $installments && isset($lines[$index]))
        {
            return $lines[$index];
        }

        return __('Installment :current of :total — :project', [
            'current' => $index + 1,
            'total' => $installments,
            'project' => $projectName,
        ]).' · '.$when->timezone('Europe/Madrid')->format('d/m/Y').' · '.number_format($amount, 2, ',', '.').' €';
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  list<string>  $taxRateIds
     * @return array{invoice: Invoice, charged: bool, hosted_invoice_url: ?string}
     */
    private function createInstallment(
        StripeClient $client,
        Project $project,
        Enterprise $enterprise,
        Team $team,
        string $stripeCustomerId,
        string $description,
        int $amount,
        array $preview,
        bool $canCharge,
        bool $immediate,
        Carbon $when,
        array $taxRateIds,
        int $index,
        int $installments,
    ): array {
        try
        {
            $payload = [
                'customer' => $stripeCustomerId,
                'auto_advance' => false,
                'pending_invoice_items_behavior' => 'exclude',
                'metadata' => [
                    'humano_project_id' => (string) $project->id,
                    'humano_balance' => '1',
                    'humano_installment' => (string) ($index + 1),
                    'humano_installments' => (string) $installments,
                    'humano_team_id' => (string) $team->id,
                ],
            ];

            if ($canCharge)
            {
                $payload['collection_method'] = 'charge_automatically';
            } else
            {
                $payload['collection_method'] = 'send_invoice';
                $payload['days_until_due'] = 15;
            }

            if (! $immediate)
            {
                $payload['automatically_finalizes_at'] = $when->timestamp;
            }

            $stripeInvoice = $client->invoices->create($payload);
            $itemPayload = [
                'customer' => $stripeCustomerId,
                'invoice' => $stripeInvoice->id,
                'currency' => 'eur',
                'description' => $description,
                'amount' => $amount * 100,
                'metadata' => [
                    'humano_project_id' => (string) $project->id,
                    'humano_balance' => '1',
                    'humano_installment' => (string) ($index + 1),
                ],
            ];
            if ($taxRateIds !== [])
            {
                $itemPayload['tax_rates'] = $taxRateIds;
            }
            $client->invoiceItems->create($itemPayload);

            $charged = false;
            if ($immediate)
            {
                $stripeInvoice = $client->invoices->finalizeInvoice($stripeInvoice->id);
                if ($canCharge && ($stripeInvoice->status ?? null) !== 'paid')
                {
                    try
                    {
                        $stripeInvoice = $client->invoices->pay($stripeInvoice->id);
                    } catch (ApiErrorException $payException)
                    {
                        Log::warning('Balance invoice created but automatic charge failed', [
                            'project_id' => $project->id,
                            'stripe_invoice_id' => $stripeInvoice->id ?? null,
                            'message' => $payException->getMessage(),
                        ]);
                    }
                }
                $charged = ($stripeInvoice->status ?? null) === 'paid';
            }
        } catch (ApiErrorException $e)
        {
            throw ValidationException::withMessages([
                'stripe' => __('Stripe error: :message', ['message' => $e->getMessage()]),
            ]);
        }

        $stripeInvoiceId = (string) ($stripeInvoice->id ?? '');
        if ($stripeInvoiceId === '')
        {
            throw new RuntimeException('Stripe did not return an invoice id.');
        }

        $vatAmount = round($amount * (((float) $preview['vat_percent']) / 100), 2);
        $installmentPreview = [
            'amount' => $amount,
            'vat_percent' => (float) $preview['vat_percent'],
            'total_with_vat' => round($amount + $vatAmount, 2),
        ];

        $invoice = $this->persistLocalInvoice(
            $project,
            $enterprise,
            $team,
            $description,
            $installmentPreview,
            $stripeInvoiceId,
            $stripeInvoice,
            $charged,
            $when,
            $index,
            $installments,
            ! $immediate,
        );

        return [
            'invoice' => $invoice,
            'charged' => $charged,
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url ?? null,
        ];
    }

    /**
     * @param  array{amount: int, vat_percent: float, total_with_vat: float}  $preview
     */
    private function persistLocalInvoice(
        Project $project,
        Enterprise $enterprise,
        Team $team,
        string $description,
        array $preview,
        string $stripeInvoiceId,
        object $stripeInvoice,
        bool $charged,
        Carbon $when,
        int $index,
        int $installments,
        bool $scheduled,
    ): Invoice {
        $attempt = 0;

        while (true)
        {
            $attempt++;

            try
            {
                return DB::transaction(function () use (
                    $project,
                    $enterprise,
                    $team,
                    $description,
                    $preview,
                    $stripeInvoiceId,
                    $stripeInvoice,
                    $charged,
                    $when,
                    $index,
                    $installments,
                    $scheduled,
                ): Invoice {
                    DatabaseSequence::sync('invoices');
                    DatabaseSequence::sync('invoice_items');

                    $currencyId = Currency::query()->where('code', 'EUR')->value('id');
                    $typeId = (int) (InvoiceType::query()->orderBy('id')->value('id') ?? 1);
                    $total = (float) $preview['total_with_vat'];
                    $dueDate = $when->copy()->timezone('Europe/Madrid')->toDateString();

                    $invoice = Invoice::withoutGlobalScopes()->create([
                        'team_id' => $team->id,
                        'enterprise_id' => $enterprise->id,
                        'billing_id' => $enterprise->enterpriseBillingAddress()?->id,
                        'type_id' => $typeId,
                        'operation' => 'sell',
                        'number' => (string) ($stripeInvoice->number ?? ('ST-'.$stripeInvoiceId)),
                        'date' => $scheduled ? $dueDate : Carbon::now()->toDateString(),
                        'due_date' => $charged ? Carbon::now()->toDateString() : $dueDate,
                        'gross_amount' => $total,
                        'discount' => 0,
                        'total_amount' => $total,
                        'balance' => $charged ? 0 : $total,
                        'currency_id' => $currencyId,
                        'status' => 2,
                        'source_provider' => 'stripe',
                        'source_reference_id' => $stripeInvoiceId,
                        'source_synced_at' => now(),
                    ]);

                    InvoiceItem::query()->create([
                        'invoice_id' => $invoice->id,
                        'category_id' => null,
                        'description' => $description,
                        'quantity' => 1,
                        'unit_price' => (float) $preview['amount'],
                        'discount' => 0,
                        'tax_percentage' => (float) $preview['vat_percent'],
                    ]);

                    $this->rememberInstallment(
                        $project,
                        $invoice,
                        $stripeInvoice,
                        $charged,
                        $preview,
                        $description,
                        $dueDate,
                        $index,
                        $installments,
                        $scheduled,
                    );

                    return $invoice;
                });
            } catch (UniqueConstraintViolationException $e)
            {
                if ($attempt >= 2)
                {
                    throw $e;
                }

                DatabaseSequence::sync('invoices');
                DatabaseSequence::sync('invoice_items');
            }
        }
    }

    /**
     * @param  array{amount: int, vat_percent: float, total_with_vat: float}  $preview
     */
    private function rememberInstallment(
        Project $project,
        Invoice $invoice,
        object $stripeInvoice,
        bool $charged,
        array $preview,
        string $description,
        string $dueDate,
        int $index,
        int $installments,
        bool $scheduled,
    ): void {
        $data = is_array($project->data) ? $project->data : [];
        $rows = is_array($data['balance_invoices'] ?? null) ? $data['balance_invoices'] : [];
        $rows[] = [
            'invoice_id' => $invoice->id,
            'stripe_invoice_id' => (string) ($stripeInvoice->id ?? ''),
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url ?? null,
            'description' => $description,
            'amount' => $preview['amount'],
            'total_with_vat' => $preview['total_with_vat'],
            'due_date' => $dueDate,
            'installment' => $index + 1,
            'installments' => $installments,
            'charged' => $charged,
            'scheduled' => $scheduled,
            'issued_at' => now()->toIso8601String(),
        ];
        $data['balance_invoices'] = $rows;
        $project->data = $data;
        $project->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function storedInvoices(Project $project): array
    {
        $rows = data_get($project->data, 'balance_invoices');

        return is_array($rows) ? array_values($rows) : [];
    }

    protected function makeStripeClient(string $secret): StripeClient
    {
        return new StripeClient($secret);
    }

    private function customerHasPaymentMethod(StripeClient $client, string $customerId): bool
    {
        try
        {
            $customer = $client->customers->retrieve($customerId, []);
            if (filled(data_get($customer, 'invoice_settings.default_payment_method')))
            {
                return true;
            }

            if (filled(data_get($customer, 'default_source')))
            {
                return true;
            }

            $paymentMethods = $client->paymentMethods->all([
                'customer' => $customerId,
                'type' => 'card',
                'limit' => 1,
            ]);

            return ! empty($paymentMethods->data);
        } catch (ApiErrorException $e)
        {
            Log::warning('Could not inspect Stripe customer payment methods', [
                'customer_id' => $customerId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function ensureExclusiveTaxRate(StripeClient $client, float $percent): string
    {
        $percent = round($percent, 2);
        $existing = $client->taxRates->all([
            'active' => true,
            'limit' => 100,
        ]);

        foreach ($existing->data as $rate)
        {
            if ((float) $rate->percentage === $percent && $rate->inclusive === false)
            {
                return (string) $rate->id;
            }
        }

        $created = $client->taxRates->create([
            'display_name' => 'IVA',
            'description' => 'IVA '.$percent.'%',
            'percentage' => $percent,
            'inclusive' => false,
            'country' => 'ES',
        ]);

        return (string) $created->id;
    }
}
