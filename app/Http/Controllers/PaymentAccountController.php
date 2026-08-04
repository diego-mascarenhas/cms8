<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentAccountDataTable;
use App\DataTables\PaymentDataTable;
use App\Http\Requests\StorePaymentAccountRequest;
use App\Http\Requests\StorePaymentAccountStatementRequest;
use App\Http\Requests\UpdatePaymentAccountRequest;
use App\Models\BankStatement;
use App\Models\Currency;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Services\Finance\PaymentAccountCompatibilityService;
use App\Services\Finance\PaymentAccountStatementUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentAccountController extends Controller
{
    public function __construct(
        private readonly PaymentAccountCompatibilityService $paymentAccountCompatibilityService,
        private readonly PaymentAccountStatementUploadService $statementUploadService,
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
        $results = $this->statementUploadService->uploadMany(
            $paymentAccount,
            $validated['files'],
            isset($validated['period_year']) ? (int) $validated['period_year'] : null,
            isset($validated['period_month']) ? (int) $validated['period_month'] : null,
        );

        $uploaded = count($results);
        $matched = collect($results)->sum(fn (array $result): int => (int) ($result['validation']['matched'] ?? 0));
        $statementOnly = collect($results)->sum(fn (array $result): int => (int) ($result['validation']['statement_only'] ?? 0));
        $paymentOnly = collect($results)->sum(fn (array $result): int => (int) ($result['validation']['payment_only'] ?? 0));

        return redirect()
            ->route('payment-account.show', $paymentAccount)
            ->with('success', __(':count extracto(s) subido(s). Coincidencias: :matched. Solo en extracto: :statement_only. Solo en pagos: :payment_only.', [
                'count' => $uploaded,
                'matched' => $matched,
                'statement_only' => $statementOnly,
                'payment_only' => $paymentOnly,
            ]));
    }

    public function downloadStatement(
        PaymentAccount $paymentAccount,
        BankStatement $statement,
    ): StreamedResponse {
        $this->authorize('view', $paymentAccount);

        if ((int) $statement->payment_account_id !== (int) $paymentAccount->id
            || (int) $statement->team_id !== (int) $paymentAccount->team_id)
        {
            abort(404);
        }

        return $this->statementUploadService->downloadStream($statement);
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
