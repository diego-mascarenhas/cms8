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

class ProjectDepositInvoiceService
{
    public const DEPOSIT_RATIO = 0.30;

    public function __construct(
        private readonly ProjectBudgetSpecService $budgetSpecService,
        private readonly EnterpriseVatRateResolver $vatRateResolver,
    ) {}

    /**
     * Preview amounts for the deposit invoice modal.
     *
     * @return array{
     *     payable_total: int,
     *     deposit_base: int,
     *     vat_percent: float,
     *     vat_applies: bool,
     *     vat_label: string,
     *     vat_amount: float,
     *     total_with_vat: float,
     *     default_description: string,
     *     stripe_customer_id: ?string,
     *     already_invoiced: bool,
     *     existing_invoice_id: ?int,
     *     existing_stripe_invoice_id: ?string
     * }
     */
    public function preview(Project $project): array
    {
        $project->loadMissing('client');
        $enterprise = $project->client;
        $totals = $this->budgetSpecService->computeQuoteTotals($project);
        $depositBase = (int) round(((int) $totals['payable_total']) * self::DEPOSIT_RATIO);
        $vat = $this->vatRateResolver->resolve($enterprise);
        $vatAmount = round($depositBase * (((float) $vat['percent']) / 100), 2);
        $existing = is_array(data_get($project->data, 'deposit_invoice'))
            ? data_get($project->data, 'deposit_invoice')
            : null;

        $projectName = trim((string) ($project->real_name ?: $project->name));
        $existingInvoiceId = isset($existing['invoice_id']) ? (int) $existing['invoice_id'] : null;
        $existingStripeInvoiceId = isset($existing['stripe_invoice_id'])
            ? (string) $existing['stripe_invoice_id']
            : null;

        return [
            'payable_total' => (int) $totals['payable_total'],
            'deposit_base' => $depositBase,
            'vat_percent' => (float) $vat['percent'],
            'vat_applies' => (bool) $vat['applies'],
            'vat_label' => (string) $vat['label'],
            'vat_amount' => $vatAmount,
            'total_with_vat' => round($depositBase + $vatAmount, 2),
            'default_description' => __('Deposit 30% — :project', ['project' => $projectName]),
            'stripe_customer_id' => $enterprise?->getStripeCustomerId(),
            // Fully done only when the local invoice row exists. A Stripe id alone
            // means a previous attempt can still be completed without double-charging.
            'already_invoiced' => $existingInvoiceId !== null,
            'existing_invoice_id' => $existingInvoiceId,
            'existing_stripe_invoice_id' => $existingStripeInvoiceId,
        ];
    }

    /**
     * Create Stripe + local sell invoice for the 30% project deposit.
     * Charges automatically when the Stripe customer has a payment method.
     * Moves the project to In progress only after a successful paid charge.
     *
     * @return array{
     *     invoice: Invoice,
     *     stripe_invoice_id: string,
     *     hosted_invoice_url: ?string,
     *     charged: bool,
     *     project_status_id: int
     * }
     */
    public function issue(Project $project, string $description, Team $team): array
    {
        if (! $project->isBudgetApproved())
        {
            throw ValidationException::withMessages([
                'project' => __('Only approved budgets can issue a deposit invoice.'),
            ]);
        }

        $preview = $this->preview($project);
        if (! empty($preview['existing_invoice_id']))
        {
            throw ValidationException::withMessages([
                'project' => __('A deposit invoice was already issued for this project.'),
            ]);
        }

        if ($preview['deposit_base'] <= 0)
        {
            throw ValidationException::withMessages([
                'project' => __('The deposit amount must be greater than zero.'),
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

        $description = trim($description);
        if ($description === '')
        {
            $description = $preview['default_description'];
        }

        $client = $this->makeStripeClient($secret);
        $canCharge = $this->customerHasPaymentMethod($client, $stripeCustomerId);
        $charged = false;
        $stripeInvoice = null;

        try
        {
            // Resume a previous attempt where Stripe succeeded but local persist failed.
            $existingStripeId = $preview['existing_stripe_invoice_id']
                ?: $this->findExistingStripeDepositInvoiceId($client, $stripeCustomerId, (int) $project->id);

            if (is_string($existingStripeId) && $existingStripeId !== '')
            {
                $stripeInvoice = $client->invoices->retrieve($existingStripeId, []);

                // Never treat an empty "paid" invoice as a successful deposit.
                if (! $this->stripeInvoiceHasDepositAmount($stripeInvoice))
                {
                    $this->voidEmptyDepositInvoice($client, $stripeInvoice);
                    $stripeInvoice = null;
                    $existingStripeId = null;
                }
            }

            if ($stripeInvoice === null)
            {
                $taxRateIds = [];
                if ($preview['vat_applies'] && $preview['vat_percent'] > 0)
                {
                    $taxRateIds[] = $this->ensureExclusiveTaxRate($client, (float) $preview['vat_percent']);
                }

                $invoicePayload = [
                    'customer' => $stripeCustomerId,
                    'auto_advance' => false,
                    // Exclude unrelated pending items; we attach the deposit line explicitly.
                    'pending_invoice_items_behavior' => 'exclude',
                    'metadata' => [
                        'humano_project_id' => (string) $project->id,
                        'humano_deposit' => '1',
                        'humano_team_id' => (string) $team->id,
                    ],
                ];

                if ($canCharge)
                {
                    $invoicePayload['collection_method'] = 'charge_automatically';
                } else
                {
                    $invoicePayload['collection_method'] = 'send_invoice';
                    $invoicePayload['days_until_due'] = 15;
                }

                // Create the draft invoice first, then attach the line explicitly.
                // Creating the item alone left a 0€ paid invoice + orphan pending item in prod.
                $stripeInvoice = $client->invoices->create($invoicePayload);

                $pendingItemId = $this->findPendingDepositInvoiceItemId(
                    $client,
                    $stripeCustomerId,
                    (int) $project->id,
                );

                if (is_string($pendingItemId) && $pendingItemId !== '')
                {
                    // Reuse the orphan pending line from the failed attempt (e.g. 165€).
                    $client->invoiceItems->update($pendingItemId, [
                        'invoice' => $stripeInvoice->id,
                    ]);
                } else
                {
                    $itemPayload = [
                        'customer' => $stripeCustomerId,
                        'invoice' => $stripeInvoice->id,
                        'currency' => 'eur',
                        'description' => $description,
                        'amount' => (int) round($preview['deposit_base'] * 100),
                        'metadata' => [
                            'humano_project_id' => (string) $project->id,
                            'humano_deposit' => '1',
                        ],
                    ];
                    if ($taxRateIds !== [])
                    {
                        $itemPayload['tax_rates'] = $taxRateIds;
                    }

                    $client->invoiceItems->create($itemPayload);
                }

                $stripeInvoice = $client->invoices->finalizeInvoice($stripeInvoice->id);

                if ($canCharge && ($stripeInvoice->status ?? null) !== 'paid')
                {
                    try
                    {
                        $stripeInvoice = $client->invoices->pay($stripeInvoice->id);
                    } catch (ApiErrorException $payException)
                    {
                        Log::warning('Deposit invoice created but automatic charge failed', [
                            'project_id' => $project->id,
                            'stripe_invoice_id' => $stripeInvoice->id ?? null,
                            'message' => $payException->getMessage(),
                        ]);
                    }
                }
            }

            $charged = ($stripeInvoice->status ?? null) === 'paid'
                && $this->stripeInvoiceHasDepositAmount($stripeInvoice);
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

        // Persist Stripe id on the project before the local invoice insert so a
        // sequence failure does not cause a duplicate Stripe charge on retry.
        $this->rememberStripeDepositReference($project, $stripeInvoiceId, $stripeInvoice, $charged, $preview, $description);

        $existingLocal = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $stripeInvoiceId)
            ->first();

        if ($existingLocal)
        {
            $this->attachDepositInvoiceToProject($project, $existingLocal, $stripeInvoice, $charged, $preview, $description);
            if ($charged)
            {
                $project->status_id = ProjectStatus::STATUS_IN_PROGRESS;
            }
            $project->save();

            return [
                'invoice' => $existingLocal,
                'stripe_invoice_id' => $stripeInvoiceId,
                'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url ?? null,
                'charged' => $charged,
                'project_status_id' => (int) $project->status_id,
            ];
        }

        $invoice = $this->persistLocalDepositInvoice(
            $project,
            $enterprise,
            $team,
            $description,
            $preview,
            $stripeInvoiceId,
            $stripeInvoice,
            $charged,
        );

        return [
            'invoice' => $invoice,
            'stripe_invoice_id' => $stripeInvoiceId,
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url ?? null,
            'charged' => $charged,
            'project_status_id' => (int) $project->fresh()->status_id,
        ];
    }

    /**
     * @param  array{
     *     deposit_base: int,
     *     vat_percent: float,
     *     total_with_vat: float
     * }  $preview
     */
    private function persistLocalDepositInvoice(
        Project $project,
        Enterprise $enterprise,
        Team $team,
        string $description,
        array $preview,
        string $stripeInvoiceId,
        object $stripeInvoice,
        bool $charged,
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
                ): Invoice {
                    DatabaseSequence::sync('invoices');
                    DatabaseSequence::sync('invoice_items');

                    $currencyId = Currency::query()->where('code', 'EUR')->value('id');
                    $typeId = (int) (InvoiceType::query()->orderBy('id')->value('id') ?? 1);
                    $total = (float) $preview['total_with_vat'];
                    $today = Carbon::now()->toDateString();

                    $invoice = Invoice::withoutGlobalScopes()->create([
                        'team_id' => $team->id,
                        'enterprise_id' => $enterprise->id,
                        'billing_id' => $enterprise->enterpriseBillingAddress()?->id,
                        'type_id' => $typeId,
                        'operation' => 'sell',
                        'number' => (string) ($stripeInvoice->number ?? ('ST-'.$stripeInvoiceId)),
                        'date' => $today,
                        'due_date' => $charged
                            ? $today
                            : Carbon::now()->addDays(15)->toDateString(),
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
                        'unit_price' => (float) $preview['deposit_base'],
                        'discount' => 0,
                        'tax_percentage' => (float) $preview['vat_percent'],
                    ]);

                    $this->attachDepositInvoiceToProject(
                        $project,
                        $invoice,
                        $stripeInvoice,
                        $charged,
                        $preview,
                        $description,
                    );
                    if ($charged)
                    {
                        $project->status_id = ProjectStatus::STATUS_IN_PROGRESS;
                    }
                    $project->save();

                    return $invoice;
                });
            } catch (UniqueConstraintViolationException $e)
            {
                if ($attempt >= 2)
                {
                    throw $e;
                }

                Log::warning('Deposit invoice local insert hit duplicate id; syncing sequences and retrying', [
                    'project_id' => $project->id,
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'message' => $e->getMessage(),
                ]);

                DatabaseSequence::sync('invoices');
                DatabaseSequence::sync('invoice_items');
            }
        }
    }

    /**
     * @param  array{
     *     deposit_base: int,
     *     vat_percent: float,
     *     total_with_vat: float
     * }  $preview
     */
    private function rememberStripeDepositReference(
        Project $project,
        string $stripeInvoiceId,
        object $stripeInvoice,
        bool $charged,
        array $preview,
        string $description,
    ): void {
        $data = is_array($project->data) ? $project->data : [];
        $data['deposit_invoice'] = array_merge(
            is_array($data['deposit_invoice'] ?? null) ? $data['deposit_invoice'] : [],
            [
                'stripe_invoice_id' => $stripeInvoiceId,
                'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url ?? null,
                'description' => $description,
                'deposit_base' => $preview['deposit_base'],
                'vat_percent' => $preview['vat_percent'],
                'total_with_vat' => $preview['total_with_vat'],
                'charged' => $charged,
                'stripe_status' => (string) ($stripeInvoice->status ?? ''),
                'issued_at' => now()->toIso8601String(),
            ],
        );
        $project->data = $data;
        $project->save();
    }

    /**
     * @param  array{
     *     deposit_base: int,
     *     vat_percent: float,
     *     total_with_vat: float
     * }  $preview
     */
    private function attachDepositInvoiceToProject(
        Project $project,
        Invoice $invoice,
        object $stripeInvoice,
        bool $charged,
        array $preview,
        string $description,
    ): void {
        $data = is_array($project->data) ? $project->data : [];
        $data['deposit_invoice'] = [
            'invoice_id' => $invoice->id,
            'stripe_invoice_id' => (string) ($stripeInvoice->id ?? data_get($data, 'deposit_invoice.stripe_invoice_id')),
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url ?? null,
            'description' => $description,
            'deposit_base' => $preview['deposit_base'],
            'vat_percent' => $preview['vat_percent'],
            'total_with_vat' => $preview['total_with_vat'],
            'charged' => $charged,
            'stripe_status' => (string) ($stripeInvoice->status ?? ''),
            'issued_at' => now()->toIso8601String(),
        ];
        $project->data = $data;
    }

    private function findPendingDepositInvoiceItemId(
        StripeClient $client,
        string $customerId,
        int $projectId,
    ): ?string {
        try
        {
            $items = $client->invoiceItems->all([
                'customer' => $customerId,
                'pending' => true,
                'limit' => 20,
            ]);

            foreach ($items->data as $item)
            {
                if ((string) data_get($item, 'metadata.humano_deposit') !== '1')
                {
                    continue;
                }

                if ((string) data_get($item, 'metadata.humano_project_id') !== (string) $projectId)
                {
                    continue;
                }

                return (string) $item->id;
            }
        } catch (ApiErrorException $e)
        {
            Log::warning('Could not search pending Stripe deposit invoice items', [
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function findExistingStripeDepositInvoiceId(
        StripeClient $client,
        string $customerId,
        int $projectId,
    ): ?string {
        try
        {
            $invoices = $client->invoices->all([
                'customer' => $customerId,
                'limit' => 20,
            ]);

            foreach ($invoices->data as $invoice)
            {
                if ((string) data_get($invoice, 'metadata.humano_deposit') !== '1')
                {
                    continue;
                }

                if ((string) data_get($invoice, 'metadata.humano_project_id') !== (string) $projectId)
                {
                    continue;
                }

                $status = (string) ($invoice->status ?? '');
                if (in_array($status, ['void', 'uncollectible', 'draft'], true))
                {
                    continue;
                }

                if (! $this->stripeInvoiceHasDepositAmount($invoice))
                {
                    continue;
                }

                return (string) $invoice->id;
            }
        } catch (ApiErrorException $e)
        {
            Log::warning('Could not search existing Stripe deposit invoices', [
                'customer_id' => $customerId,
                'project_id' => $projectId,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function stripeInvoiceHasDepositAmount(object $stripeInvoice): bool
    {
        $total = (int) ($stripeInvoice->total ?? 0);
        $amountPaid = (int) ($stripeInvoice->amount_paid ?? 0);
        $amountDue = (int) ($stripeInvoice->amount_due ?? 0);

        return max($total, $amountPaid, $amountDue) > 0;
    }

    private function voidEmptyDepositInvoice(StripeClient $client, object $stripeInvoice): void
    {
        $id = (string) ($stripeInvoice->id ?? '');
        if ($id === '')
        {
            return;
        }

        try
        {
            $status = (string) ($stripeInvoice->status ?? '');
            if (in_array($status, ['open', 'draft', 'uncollectible'], true))
            {
                $client->invoices->voidInvoice($id);
            }

            Log::warning('Ignored empty Stripe deposit invoice (no line amount)', [
                'stripe_invoice_id' => $id,
                'status' => $status,
                'total' => $stripeInvoice->total ?? null,
            ]);
        } catch (ApiErrorException $e)
        {
            Log::warning('Could not void empty Stripe deposit invoice', [
                'stripe_invoice_id' => $id,
                'message' => $e->getMessage(),
            ]);
        }
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
            $defaultPaymentMethod = data_get($customer, 'invoice_settings.default_payment_method');
            if (filled($defaultPaymentMethod))
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
