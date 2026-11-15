<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankStatementLinkInvoiceRequest;
use App\Http\Requests\StoreBankStatementTransferRequest;
use App\Http\Requests\StorePaymentAccountStatementRequest;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Services\Finance\BankStatementInvoiceCandidateService;
use App\Services\Finance\BankStatementReconcileService;
use App\Services\Finance\PaymentAccountStatementUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentAccountStatementController extends Controller
{
    public function __construct(
        private readonly PaymentAccountStatementUploadService $statementUploadService,
        private readonly BankStatementReconcileService $statementReconcileService,
        private readonly BankStatementInvoiceCandidateService $invoiceCandidateService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentAccount::class);

        $accounts = PaymentAccount::query()
            ->with('currency:id,code')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency_id'])
            ->map(fn (PaymentAccount $account): array => [
                'id' => (int) $account->id,
                'name' => (string) $account->name,
                'code' => (string) $account->code,
                'currency_code' => strtoupper((string) ($account->currency?->code ?? '')),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function store(
        StorePaymentAccountStatementRequest $request,
        PaymentAccount $paymentAccount,
    ): JsonResponse {
        $this->authorize('update', $paymentAccount);

        $validated = $request->validated();
        [$month, $quarter] = $this->statementPeriod($validated);
        $statementBalance = isset($validated['statement_balance']) && $validated['statement_balance'] !== ''
            ? (float) $validated['statement_balance']
            : null;

        $results = $this->statementUploadService->uploadMany(
            $paymentAccount,
            $validated['files'],
            isset($validated['period_year']) ? (int) $validated['period_year'] : null,
            $month,
            $quarter,
            $statementBalance,
        );

        $statements = collect($results)->map(function (array $result): array
        {
            /** @var BankStatement $statement */
            $statement = $result['statement'];
            $validation = $result['validation'] ?? [];

            return [
                'id' => (int) $statement->id,
                'filename' => (string) $statement->original_filename,
                'period_label' => $statement->periodLabel(),
                'matched_lines' => (int) ($validation['matched'] ?? 0),
                'statement_only_lines' => (int) ($validation['statement_only'] ?? 0),
                'payment_only_lines' => (int) ($validation['payment_only'] ?? 0),
                'balanced' => (bool) ($validation['balanced'] ?? false),
                'difference' => array_key_exists('difference', $validation) && $validation['difference'] !== null
                    ? round((float) $validation['difference'], 2)
                    : null,
                'book_total' => array_key_exists('book_total', $validation) && $validation['book_total'] !== null
                    ? round((float) $validation['book_total'], 2)
                    : null,
                'statement_total' => array_key_exists('statement_total', $validation) && $validation['statement_total'] !== null
                    ? round((float) $validation['statement_total'], 2)
                    : null,
            ];
        })->values()->all();

        $uploaded = count($statements);
        $balanced = collect($statements)->where('balanced', true)->count();
        $open = collect($statements)->filter(function (array $row): bool
        {
            $difference = $row['difference'] ?? null;

            return $difference !== null && abs((float) $difference) > 0.05;
        })->count();

        return response()->json([
            'success' => true,
            'uploaded' => $uploaded,
            'balanced' => $balanced,
            'open' => $open,
            'statements' => $statements,
            'message' => __(':count extracto(s) subido(s). Asociados: :balanced. Con diferencia: :open.', [
                'count' => $uploaded,
                'balanced' => $balanced,
                'open' => $open,
            ]),
        ]);
    }

    public function statements(PaymentAccount $paymentAccount): JsonResponse
    {
        $this->authorize('view', $paymentAccount);
        $paymentAccount->loadMissing('currency:id,code');

        $statements = BankStatement::query()
            ->where('payment_account_id', $paymentAccount->id)
            ->where('team_id', $paymentAccount->team_id)
            ->withCount('lines')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->get()
            ->map(function (BankStatement $statement): array
            {
                $summary = $statement->validation_summary ?? [];

                return [
                    'id' => (int) $statement->id,
                    'filename' => (string) ($statement->original_filename ?? ''),
                    'period_label' => $statement->periodLabel(),
                    'downloadable' => $statement->isDownloadable(),
                    'file_size' => $statement->file_size ? (int) $statement->file_size : null,
                    'created_at' => $statement->created_at?->toIso8601String(),
                    'line_count' => (int) ($summary['line_count'] ?? $statement->lines_count),
                    'matched_lines' => (int) ($summary['matched'] ?? 0),
                    'statement_only_lines' => (int) ($summary['statement_only'] ?? 0),
                    'payment_only_lines' => (int) ($summary['payment_only'] ?? 0),
                    'balanced' => (bool) ($summary['balanced'] ?? false),
                    'difference' => array_key_exists('difference', $summary) && $summary['difference'] !== null
                        ? round((float) $summary['difference'], 2)
                        : null,
                    'book_total' => array_key_exists('book_total', $summary) && $summary['book_total'] !== null
                        ? round((float) $summary['book_total'], 2)
                        : null,
                    'statement_total' => array_key_exists('statement_total', $summary) && $summary['statement_total'] !== null
                        ? round((float) $summary['statement_total'], 2)
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'account' => [
                'id' => (int) $paymentAccount->id,
                'name' => (string) $paymentAccount->name,
                'currency_code' => strtoupper((string) ($paymentAccount->currency?->code ?? '')),
            ],
            'statements' => $statements,
        ]);
    }

    public function reconcile(Request $request, PaymentAccount $paymentAccount, BankStatement $statement): JsonResponse
    {
        $this->authorize('view', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);

        $filter = (string) $request->query('filter', 'open');
        if (! in_array($filter, ['open', 'matched', 'dismissed', 'all'], true))
        {
            $filter = 'open';
        }

        $paymentAccount->loadMissing('currency:id,code');
        $statement->load([
            'lines' => fn ($query) => $query->with([
                'payment:id,remarks,amount,account_id,invoice_id,document_amount,exchange_rate,source_provider,source_reference_id',
                'payment.invoice:id,number,currency_id,operation',
                'payment.invoice.currency:id,code',
            ])->orderByDesc('occurred_at')->orderByDesc('id'),
        ]);

        $allLines = $statement->lines;
        $lines = $allLines->filter(function (BankStatementLine $line) use ($filter): bool
        {
            return match ($filter)
            {
                'matched' => $line->payment_id !== null && ! $line->isDismissed(),
                'dismissed' => $line->isDismissed(),
                'open' => $line->payment_id === null && ! $line->isDismissed(),
                default => true,
            };
        })->values();

        $transferAccounts = PaymentAccount::query()
            ->with('currency:id,code')
            ->where('id', '!=', $paymentAccount->id)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency_id'])
            ->map(fn (PaymentAccount $account): array => [
                'id' => (int) $account->id,
                'name' => (string) $account->name,
                'code' => (string) $account->code,
                'currency_code' => strtoupper((string) ($account->currency?->code ?? '')),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'filter' => $filter,
            'account' => [
                'id' => (int) $paymentAccount->id,
                'name' => (string) $paymentAccount->name,
                'currency_code' => strtoupper((string) ($paymentAccount->currency?->code ?? '')),
            ],
            'statement' => [
                'id' => (int) $statement->id,
                'filename' => (string) ($statement->original_filename ?? ''),
                'period_label' => $statement->periodLabel(),
                'summary' => $statement->validation_summary ?? [],
            ],
            'counts' => [
                'open' => $allLines->filter(fn (BankStatementLine $line): bool => $line->payment_id === null && ! $line->isDismissed())->count(),
                'matched' => $allLines->filter(fn (BankStatementLine $line): bool => $line->payment_id !== null && ! $line->isDismissed())->count(),
                'dismissed' => $allLines->filter(fn (BankStatementLine $line): bool => $line->isDismissed())->count(),
                'all' => $allLines->count(),
            ],
            'transfer_accounts' => $transferAccounts,
            'lines' => $lines->map(function (BankStatementLine $line): array
            {
                $payment = $line->payment;
                $invoice = $payment?->invoice;
                $invoiceId = $payment?->invoice_id ? (int) $payment->invoice_id : null;
                $sourceReference = (string) ($payment?->source_reference_id ?? '');
                $canUnlink = $payment !== null && (
                    $sourceReference === 'statement-line:'.$line->id
                    || (
                        (string) ($payment->source_provider ?? '') === 'transfer'
                        && str_starts_with($sourceReference, 'transfer:')
                        && str_ends_with($sourceReference, ':in')
                    )
                );

                return [
                    'id' => (int) $line->id,
                    'external_id' => $line->external_id,
                    'occurred_at' => $line->occurred_at?->toDateString(),
                    'amount' => round((float) $line->amount, 2),
                    'currency' => strtoupper((string) ($line->currency ?? '')),
                    'reference' => $line->reference,
                    'description' => $line->description,
                    'match_status' => $line->match_status,
                    'dismissed' => $line->isDismissed(),
                    'payment_id' => $line->payment_id ? (int) $line->payment_id : null,
                    'payment_remarks' => $payment?->remarks,
                    'payment_document_amount' => $payment?->document_amount !== null
                        ? round((float) $payment->document_amount, 2)
                        : null,
                    'payment_exchange_rate' => $payment?->exchange_rate !== null
                        ? round((float) $payment->exchange_rate, 6)
                        : null,
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoice?->number,
                    'invoice_operation' => $invoice?->operation,
                    'invoice_currency_code' => $invoice?->currency
                        ? strtoupper((string) $invoice->currency->code)
                        : null,
                    'can_unlink' => $canUnlink,
                ];
            })->values(),
        ]);
    }

    public function dismissLine(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): JsonResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        $this->statementReconcileService->dismiss($line);

        return response()->json([
            'success' => true,
            'message' => __('Movimiento marcado como ignorado.'),
        ]);
    }

    public function undismissLine(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): JsonResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        $this->statementReconcileService->undismiss($line);

        return response()->json([
            'success' => true,
            'message' => __('Movimiento vuelto a pendientes.'),
        ]);
    }

    public function transferLine(
        StoreBankStatementTransferRequest $request,
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): JsonResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        $fromAccount = PaymentAccount::query()->findOrFail((int) $request->validated('from_account_id'));

        if ((int) $fromAccount->team_id !== (int) $paymentAccount->team_id)
        {
            abort(404);
        }

        try
        {
            $result = $this->statementReconcileService->createTransfer(
                $line,
                $paymentAccount,
                $fromAccount,
                $request->validated('remarks') ?? null,
            );
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Traspaso registrado y movimiento asociado.'),
            'inflow_payment_id' => (int) $result['inflow']->id,
            'outflow_payment_id' => (int) $result['outflow']->id,
            'line_id' => (int) $result['line']->id,
        ]);
    }

    public function invoiceCandidates(
        Request $request,
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): JsonResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        $payload = $this->invoiceCandidateService->candidatesForLine(
            $line,
            (int) $paymentAccount->team_id,
            trim((string) $request->query('search', '')),
        );

        return response()->json([
            'success' => true,
            ...$payload,
        ]);
    }

    public function linkInvoice(
        StoreBankStatementLinkInvoiceRequest $request,
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): JsonResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        $invoice = Invoice::query()
            ->where('team_id', $paymentAccount->team_id)
            ->findOrFail((int) $request->validated('invoice_id'));

        try
        {
            $result = $this->statementReconcileService->linkToInvoice(
                $line,
                $invoice,
                $paymentAccount,
                isset($request->validated()['type_id']) ? (int) $request->validated('type_id') : null,
                $request->validated('remarks') ?? null,
            );
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $payment = $result['payment'];

        return response()->json([
            'success' => true,
            'message' => __('Pago asociado a la factura y movimiento asociado.'),
            'payment_id' => (int) $payment->id,
            'invoice_id' => (int) $result['invoice']->id,
            'line_id' => (int) $result['line']->id,
            'invoice_balance' => round((float) $result['invoice']->balance, 2),
            'document_amount' => $payment->document_amount !== null
                ? round((float) $payment->document_amount, 2)
                : null,
            'exchange_rate' => $payment->exchange_rate !== null
                ? round((float) $payment->exchange_rate, 6)
                : null,
            'invoice_operation' => (string) $result['invoice']->operation,
        ]);
    }

    public function unlinkLine(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): JsonResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        try
        {
            $result = $this->statementReconcileService->unlinkMatch($line, $paymentAccount);
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Asociación revertida. El movimiento volvió a pendientes.'),
            'line_id' => (int) $result['line']->id,
            'invoice_id' => $result['invoice']?->id ? (int) $result['invoice']->id : null,
            'invoice_balance' => $result['invoice']
                ? round((float) $result['invoice']->balance, 2)
                : null,
            'deleted_payment_ids' => $result['deleted_payment_ids'],
        ]);
    }

    public function download(PaymentAccount $paymentAccount, BankStatement $statement): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);

        try
        {
            return $this->statementUploadService->downloadStream($statement);
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }
    }

    public function destroy(PaymentAccount $paymentAccount, BankStatement $statement): JsonResponse
    {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);

        $this->statementReconcileService->deleteStatement($statement);

        return response()->json([
            'success' => true,
            'message' => __('Extracto eliminado.'),
        ]);
    }

    private function assertStatementBelongsToAccount(PaymentAccount $paymentAccount, BankStatement $statement): void
    {
        if ((int) $statement->payment_account_id !== (int) $paymentAccount->id
            || (int) $statement->team_id !== (int) $paymentAccount->team_id)
        {
            abort(404);
        }
    }

    private function assertLineBelongsToStatement(BankStatement $statement, BankStatementLine $line): void
    {
        if ((int) $line->bank_statement_id !== (int) $statement->id)
        {
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: int|null, 1: int|null}
     */
    private function statementPeriod(array $validated): array
    {
        $period = (string) ($validated['period'] ?? '');

        if (preg_match('/^m:(\d{1,2})$/', $period, $matches) === 1)
        {
            return [(int) $matches[1], null];
        }

        if (preg_match('/^q:([1-4])$/', $period, $matches) === 1)
        {
            return [null, (int) $matches[1]];
        }

        return [
            isset($validated['period_month']) ? (int) $validated['period_month'] : null,
            null,
        ];
    }
}
