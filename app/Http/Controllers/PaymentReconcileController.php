<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentReconcileDataTable;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Services\Billing\MercadoPagoPaymentImportService;
use App\Services\Billing\MercadoPagoPaymentMatchUndoService;
use App\Services\Billing\PaymentReconcileQueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentReconcileController extends Controller
{
    public function __construct(
        private readonly PaymentReconcileQueueService $queueService,
        private readonly MercadoPagoPaymentImportService $importService,
        private readonly MercadoPagoPaymentMatchUndoService $undoService,
    ) {}

    public function index(PaymentReconcileDataTable $dataTable)
    {
        $this->authorize('create', Payment::class);

        if (request()->boolean('rebuild'))
        {
            $this->forgetSuggestionCache((int) auth()->user()->currentTeam->id);
        }

        return $dataTable->render('payments.reconcile');
    }

    public function accept(Request $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validate([
            'sync_id' => ['required', 'integer'],
            'enterprise_id' => ['required', 'integer'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer'],
        ]);

        $sync = $this->resolveTeamSync((int) $validated['sync_id']);
        if (! $sync instanceof PaymentSync)
        {
            return redirect()
                ->route('payments.reconcile')
                ->with('error', __('payment_sync.mercadopago.errors.import_failed'));
        }

        if ($this->importService->isAlreadyImported($sync))
        {
            return redirect()
                ->route('payments.reconcile')
                ->with('warning', __('payment_sync.mercadopago.errors.already_imported'));
        }

        $payment = $this->importService->importFromPaymentSync(
            $sync,
            fallbackEmail: false,
            linkCodeOnEmailMatch: false,
            dryRun: false,
            forceEnterpriseId: (int) $validated['enterprise_id'],
            forceInvoiceIds: array_map('intval', $validated['invoice_ids'] ?? []),
            remarksOverride: null,
        );

        $this->forgetSuggestionCache((int) $sync->team_id);

        if ($payment === null)
        {
            return redirect()
                ->route('payments.reconcile')
                ->with('error', __('payment_sync.mercadopago.errors.import_failed'));
        }

        return redirect()
            ->route('payments.reconcile')
            ->with('success', __('payment_sync.mercadopago.success', [
                'reference' => $sync->external_id,
            ]));
    }

    public function undo(Request $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validate([
            'sync_id' => ['required', 'integer'],
        ]);

        $sync = $this->resolveTeamSync((int) $validated['sync_id']);
        if (! $sync instanceof PaymentSync)
        {
            return redirect()
                ->route('payments.reconcile')
                ->with('error', __('payment_sync.reconcile.errors.undo_failed'));
        }

        $result = $this->undoService->undo($sync);
        $this->forgetSuggestionCache((int) $sync->team_id);

        return redirect()
            ->route('payments.reconcile')
            ->with('success', __('payment_sync.reconcile.success_undo', [
                'count' => $result['deleted_payments'],
            ]));
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validate([
            'sync_id' => ['required', 'integer'],
            'statement_line_id' => ['nullable', 'integer'],
        ]);

        $sync = $this->resolveTeamSync((int) $validated['sync_id']);
        if (! $sync instanceof PaymentSync)
        {
            return redirect()
                ->route('payments.reconcile')
                ->with('error', __('payment_sync.reconcile.errors.undo_failed'));
        }

        $this->queueService->dismissMismatch((int) auth()->user()->currentTeam->id, [
            'sync_id' => (int) $sync->id,
            'statement_line_id' => (int) ($validated['statement_line_id'] ?? 0),
        ]);

        $this->forgetSuggestionCache((int) $sync->team_id);

        return redirect()
            ->route('payments.reconcile')
            ->with('success', __('payment_sync.reconcile.success_dismiss'));
    }

    public function skip(Request $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        return redirect()->route('payments.reconcile');
    }

    private function resolveTeamSync(int $syncId): ?PaymentSync
    {
        if ($syncId <= 0)
        {
            return null;
        }

        $sync = PaymentSync::query()->find($syncId);
        if (! $sync instanceof PaymentSync)
        {
            return null;
        }

        if ((int) $sync->team_id !== (int) auth()->user()->currentTeam->id)
        {
            return null;
        }

        return $sync;
    }

    private function forgetSuggestionCache(int $teamId): void
    {
        Cache::forget('payment_reconcile.suggestions.'.$teamId);
    }
}
