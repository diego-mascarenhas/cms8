<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentAccountRequest;
use App\Http\Requests\UpdatePaymentAccountRequest;
use App\Models\Currency;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Services\Finance\PaymentAccountCompatibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PaymentAccountController extends Controller
{
    public function __construct(
        private readonly PaymentAccountCompatibilityService $paymentAccountCompatibilityService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', PaymentAccount::class);

        $teamId = (int) auth()->user()->currentTeam->id;

        $accounts = PaymentAccount::withoutGlobalScopes()
            ->with(['currency', 'paymentTypes'])
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        return view('payment-account.index', compact('accounts'));
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
