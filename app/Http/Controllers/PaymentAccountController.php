<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentAccountDataTable;
use App\DataTables\PaymentDataTable;
use App\Http\Requests\StoreBankStatementLinkInvoiceRequest;
use App\Http\Requests\StoreBankStatementTransferRequest;
use App\Http\Requests\StorePaymentAccountRequest;
use App\Http\Requests\StorePaymentAccountStatementRequest;
use App\Http\Requests\UpdatePaymentAccountRequest;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Services\Finance\BankStatementInvoiceCandidateService;
use App\Services\Finance\BankStatementReconcileService;
use App\Services\Finance\PaymentAccountCompatibilityService;
use App\Services\Finance\PaymentAccountStatementUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentAccountController extends Controller
{
    public function __construct(
        private readonly PaymentAccountCompatibilityService $paymentAccountCompatibilityService,
        private readonly PaymentAccountStatementUploadService $statementUploadService,
        private readonly BankStatementReconcileService $statementReconcileService,
        private readonly BankStatementInvoiceCandidateService $invoiceCandidateService,
    ) {}

    public function index(PaymentAccountDataTable $dataTable)
    {
        $this->authorize('viewAny', PaymentAccount::class);

        return $dataTable->render('payment-account.index');
    }

    public function show(PaymentAccount $paymentAccount, PaymentDataTable $dataTable)
    {
        $this->authorize('view', $paymentAccount);

        $paymentAccount->load('currency');

        $statements = BankStatement::query()
            ->where('payment_account_id', $paymentAccount->id)
            ->where('team_id', $paymentAccount->team_id)
            ->withCount('lines')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->get();

        return $dataTable
            ->forAccount((int) $paymentAccount->id)
            ->render('payment-account.show', [
                'account' => $paymentAccount,
                'balance' => (float) $paymentAccount->total_amount,
                'statements' => $statements,
            ]);
    }

    public function storeStatements(
        StorePaymentAccountStatementRequest $request,
        PaymentAccount $paymentAccount,
    ): RedirectResponse {
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

        $uploaded = count($results);
        $balanced = collect($results)->filter(
            fn (array $result): bool => ($result['validation']['balanced'] ?? false) === true,
        )->count();
        $open = collect($results)->filter(function (array $result): bool
        {
            $difference = $result['validation']['difference'] ?? null;

            return $difference !== null && abs((float) $difference) > 0.05;
        })->count();

        $latest = collect($results)->last();
        /** @var \App\Models\BankStatement|null $latestStatement */
        $latestStatement = is_array($latest) ? ($latest['statement'] ?? null) : null;

        if ($latestStatement instanceof BankStatement)
        {
            return redirect()
                ->route('payment-account.statements.reconcile', [$paymentAccount, $latestStatement, 'filter' => 'open'])
                ->with('success', __(':count extracto(s) subido(s). Asociados: :balanced. Con diferencia: :open.', [
                    'count' => $uploaded,
                    'balanced' => $balanced,
                    'open' => $open,
                ]));
        }

        return redirect()
            ->route('payment-account.show', $paymentAccount)
            ->with('success', __(':count extracto(s) subido(s). Asociados: :balanced. Con diferencia: :open.', [
                'count' => $uploaded,
                'balanced' => $balanced,
                'open' => $open,
            ]));
    }

    public function downloadStatement(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
    ): StreamedResponse {
        $this->authorize('view', $paymentAccount);

        $this->assertStatementBelongsToAccount($paymentAccount, $statement);

        return $this->statementUploadService->downloadStream($statement);
    }

    public function reconcileStatement(
        Request $request,
        PaymentAccount $paymentAccount,
        BankStatement $statement,
    ): View {
        $this->authorize('view', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);

        $filter = (string) $request->query('filter', 'open');
        if (! in_array($filter, ['open', 'matched', 'dismissed', 'all'], true))
        {
            $filter = 'open';
        }

        $paymentAccount->load('currency');
        $statement->load([
            'lines' => fn ($query) => $query
                ->with('payment:id,remarks,amount,account_id,invoice_id,document_amount,exchange_rate,source_provider,source_reference_id')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id'),
        ]);

        $lines = $statement->lines->filter(function (BankStatementLine $line) use ($filter): bool
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
            ->get(['id', 'name', 'code', 'currency_id']);

        $counts = [
            'open' => $statement->lines->filter(fn (BankStatementLine $line): bool => $line->payment_id === null && ! $line->isDismissed())->count(),
            'matched' => $statement->lines->filter(fn (BankStatementLine $line): bool => $line->payment_id !== null && ! $line->isDismissed())->count(),
            'dismissed' => $statement->lines->filter(fn (BankStatementLine $line): bool => $line->isDismissed())->count(),
            'all' => $statement->lines->count(),
        ];

        $invoiceCandidatesByLine = [];

        foreach ($lines as $line)
        {
            if ($line->payment_id !== null || $line->isDismissed())
            {
                continue;
            }

            $invoiceCandidatesByLine[(int) $line->id] = $this->invoiceCandidateService->candidatesForLine(
                $line,
                (int) $paymentAccount->team_id,
            );
        }

        return view('payment-account.reconcile', [
            'account' => $paymentAccount,
            'statement' => $statement,
            'lines' => $lines,
            'filter' => $filter,
            'counts' => $counts,
            'transferAccounts' => $transferAccounts,
            'invoiceCandidatesByLine' => $invoiceCandidatesByLine,
            'summary' => $statement->validation_summary ?? [],
        ]);
    }

    public function dismissStatementLine(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): RedirectResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        $this->statementReconcileService->dismiss($line);

        return redirect()
            ->route('payment-account.statements.reconcile', [$paymentAccount, $statement, 'filter' => 'open'])
            ->with('success', __('Movimiento marcado como ignorado.'));
    }

    public function undismissStatementLine(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): RedirectResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        $this->statementReconcileService->undismiss($line);

        return redirect()
            ->route('payment-account.statements.reconcile', [$paymentAccount, $statement, 'filter' => 'open'])
            ->with('success', __('Movimiento vuelto a pendientes.'));
    }

    public function transferStatementLine(
        StoreBankStatementTransferRequest $request,
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): RedirectResponse {
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
            $this->statementReconcileService->createTransfer(
                $line,
                $paymentAccount,
                $fromAccount,
                $request->validated('remarks') ?? null,
            );
        } catch (RuntimeException $exception)
        {
            return redirect()
                ->route('payment-account.statements.reconcile', [$paymentAccount, $statement, 'filter' => 'open'])
                ->withErrors(['transfer' => $exception->getMessage()]);
        }

        return redirect()
            ->route('payment-account.statements.reconcile', [$paymentAccount, $statement, 'filter' => 'matched'])
            ->with('success', __('Traspaso registrado y movimiento asociado.'));
    }

    public function linkStatementLineInvoice(
        StoreBankStatementLinkInvoiceRequest $request,
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): RedirectResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        $invoice = Invoice::query()
            ->where('team_id', $paymentAccount->team_id)
            ->findOrFail((int) $request->validated('invoice_id'));

        try
        {
            $this->statementReconcileService->linkToInvoice(
                $line,
                $invoice,
                $paymentAccount,
                isset($request->validated()['type_id']) ? (int) $request->validated('type_id') : null,
                $request->validated('remarks') ?? null,
            );
        } catch (RuntimeException $exception)
        {
            return redirect()
                ->route('payment-account.statements.reconcile', [$paymentAccount, $statement, 'filter' => 'open'])
                ->withErrors(['link_invoice' => $exception->getMessage()]);
        }

        return redirect()
            ->route('payment-account.statements.reconcile', [$paymentAccount, $statement, 'filter' => 'matched'])
            ->with('success', __('Pago asociado a la factura y movimiento asociado.'));
    }

    public function unlinkStatementLine(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
        BankStatementLine $line,
    ): RedirectResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);
        $this->assertLineBelongsToStatement($statement, $line);

        try
        {
            $this->statementReconcileService->unlinkMatch($line, $paymentAccount);
        } catch (RuntimeException $exception)
        {
            return redirect()
                ->route('payment-account.statements.reconcile', [$paymentAccount, $statement, 'filter' => 'matched'])
                ->withErrors(['unlink' => $exception->getMessage()]);
        }

        return redirect()
            ->route('payment-account.statements.reconcile', [$paymentAccount, $statement, 'filter' => 'open'])
            ->with('success', __('Asociación revertida. El movimiento volvió a pendientes.'));
    }

    public function destroyStatement(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
    ): RedirectResponse {
        $this->authorize('update', $paymentAccount);
        $this->assertStatementBelongsToAccount($paymentAccount, $statement);

        $this->statementReconcileService->deleteStatement($statement);

        return redirect()
            ->route('payment-account.show', $paymentAccount)
            ->with('success', __('Extracto eliminado.'));
    }

    public function create(): View
    {
        $this->authorize('create', PaymentAccount::class);

        return view('payment-account.form', [
            'data' => new PaymentAccount(['status' => 1]),
            'currencies' => $this->currencies(),
            'paymentTypes' => $this->paymentTypes(),
            'selectedPaymentTypeIds' => old('payment_type_ids', []),
        ]);
    }

    public function store(StorePaymentAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', PaymentAccount::class);

        $validated = $request->validated();

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) auth()->user()->currentTeam->id,
            'enterprise_id' => null,
            'code' => strtoupper((string) $validated['code']),
            'name' => (string) $validated['name'],
            'currency_id' => (int) $validated['currency_id'],
            'status' => (int) $validated['status'],
        ]);

        $this->paymentAccountCompatibilityService->syncConfiguredPaymentTypes(
            $account,
            $validated['payment_type_ids'],
        );

        return redirect()
            ->route('payment-account.index')
            ->with('success', 'Cuenta de pago creada correctamente.');
    }

    public function edit(PaymentAccount $paymentAccount): View
    {
        $this->authorize('update', $paymentAccount);

        $paymentAccount->load('paymentTypes');

        return view('payment-account.form', [
            'data' => $paymentAccount,
            'currencies' => $this->currencies(),
            'paymentTypes' => $this->paymentTypes(),
            'selectedPaymentTypeIds' => old(
                'payment_type_ids',
                $paymentAccount->paymentTypes->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ),
        ]);
    }

    public function update(UpdatePaymentAccountRequest $request, PaymentAccount $paymentAccount): RedirectResponse
    {
        $this->authorize('update', $paymentAccount);

        $validated = $request->validated();

        $paymentAccount->update([
            'code' => strtoupper((string) $validated['code']),
            'name' => (string) $validated['name'],
            'currency_id' => (int) $validated['currency_id'],
            'status' => (int) $validated['status'],
        ]);

        $this->paymentAccountCompatibilityService->syncConfiguredPaymentTypes(
            $paymentAccount,
            $validated['payment_type_ids'],
        );

        return redirect()
            ->route('payment-account.index')
            ->with('success', 'Cuenta de pago actualizada correctamente.');
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
     * @return array{0: ?int, 1: ?int}
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Currency>
     */
    private function currencies()
    {
        return Currency::query()
            ->active()
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PaymentType>
     */
    private function paymentTypes()
    {
        $query = PaymentType::query()->orderBy('name');

        if (Schema::hasColumn('payment_types', 'is_active'))
        {
            $query->where('is_active', true);
        }

        return $query->get(['id', 'name']);
    }
}
