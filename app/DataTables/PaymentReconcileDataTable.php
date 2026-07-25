<?php

namespace App\DataTables;

use App\Models\BankStatementLine;
use App\Models\Enterprise;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Services\Billing\PaymentReconcileQueueService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PaymentReconcileDataTable extends DataTable
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $suggestionsBySyncId = null;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $suggestions = $this->suggestionsBySyncId();

        return (new EloquentDataTable($query))
            ->addColumn('occurred_at_label', function (PaymentSync $sync): string
            {
                $line = $this->statementLineFor($sync);
                $date = $line?->occurred_at ?? $sync->charge_created_at;

                return $date ? Carbon::parse($date)->format('d/m/Y H:i') : '—';
            })
            ->addColumn('amount_label', function (PaymentSync $sync): string
            {
                $line = $this->statementLineFor($sync);
                if ($line !== null)
                {
                    return e(number_format((float) $line->amount, 2, ',', '.')
                        .' '.strtoupper((string) ($line->currency ?: 'ARS')));
                }

                $currency = strtoupper((string) $sync->currency);
                $cents = (int) $sync->amount_net_cents;
                $amount = in_array($currency, ['CLP', 'UYU', 'PYG'], true)
                    ? (float) $cents
                    : round($cents / 100, 2);

                return e(number_format($amount, 2, ',', '.').' '.$currency);
            })
            ->addColumn('statement_payer', function (PaymentSync $sync): string
            {
                $line = $this->statementLineFor($sync);
                $name = $line?->payer_name ?: $sync->settlementPayerName();
                $doc = $line?->payer_id_number ?: $sync->settlementPayerIdNumber();

                if (blank($name))
                {
                    return '<span class="text-muted">'.e(__('payment_sync.mercadopago.payer_unknown')).'</span>';
                }

                $html = e($name);
                if (filled($doc))
                {
                    $html .= '<div class="small text-muted">'.e($doc).'</div>';
                }

                return $html;
            })
            ->addColumn('humano_client', function (PaymentSync $sync) use ($suggestions): string
            {
                $payment = $sync->importedMercadoPagoPayment();
                if ($payment?->enterprise instanceof Enterprise)
                {
                    return e((string) $payment->enterprise->name);
                }

                $suggestion = $suggestions[(int) $sync->id] ?? null;
                if (is_array($suggestion) && ($suggestion['kind'] ?? '') === 'suggestion')
                {
                    return e((string) ($suggestion['enterprise_name'] ?? '—'))
                        .'<div class="small text-muted">'.e(__('payment_sync.reconcile.suggested')).'</div>';
                }

                return '<span class="text-muted">—</span>';
            })
            ->addColumn('invoice_label', function (PaymentSync $sync) use ($suggestions): string
            {
                $payment = $sync->importedMercadoPagoPayment();
                if ($payment?->invoice)
                {
                    $number = (string) ($payment->invoice->number ?: '#'.$payment->invoice->id);

                    return '<a href="'.e(route('invoice.show', $payment->invoice->id)).'" class="badge bg-label-secondary text-decoration-none">'
                        .e($number).'</a>';
                }

                $suggestion = $suggestions[(int) $sync->id] ?? null;
                if (is_array($suggestion) && ! empty($suggestion['invoice_ids'][0]))
                {
                    $invoiceId = (int) $suggestion['invoice_ids'][0];
                    $number = (string) ($suggestion['invoice_numbers'][0] ?? ('#'.$invoiceId));

                    return '<a href="'.e(route('invoice.show', $invoiceId)).'" class="badge bg-label-secondary text-decoration-none">'
                        .e($number).'</a>';
                }

                return '<span class="text-muted">—</span>';
            })
            ->addColumn('reconcile_status', function (PaymentSync $sync) use ($suggestions): string
            {
                return $this->statusBadge($sync, $suggestions[(int) $sync->id] ?? null);
            })
            ->editColumn('external_id', function (PaymentSync $sync): string
            {
                return '<code>'.e((string) $sync->external_id).'</code>';
            })
            ->addColumn('action', function (PaymentSync $sync) use ($suggestions): string
            {
                return view('payments.reconcile.action', [
                    'sync' => $sync,
                    'suggestion' => $suggestions[(int) $sync->id] ?? null,
                    'payment' => $sync->importedMercadoPagoPayment(),
                    'status' => $this->statusKey($sync, $suggestions[(int) $sync->id] ?? null),
                    'statementLine' => $this->statementLineFor($sync),
                ])->render();
            })
            ->filterColumn('statement_payer', function ($query, $keyword): void
            {
                $this->applyStatementPayerSearch($query, (string) $keyword);
            })
            ->filterColumn('humano_client', function ($query, $keyword): void
            {
                $this->applyHumanoClientSearch($query, (string) $keyword);
            })
            ->filterColumn('invoice_label', function ($query, $keyword): void
            {
                $this->applyInvoiceSearch($query, (string) $keyword);
            })
            ->filterColumn('amount_label', function ($query, $keyword): void
            {
                $this->applyAmountSearch($query, (string) $keyword);
            })
            ->filterColumn('external_id', function ($query, $keyword): void
            {
                $keyword = trim((string) $keyword);
                if ($keyword === '')
                {
                    return;
                }

                $query->where('payment_syncs.external_id', 'like', '%'.$keyword.'%');
            })
            ->rawColumns(['statement_payer', 'humano_client', 'invoice_label', 'reconcile_status', 'external_id', 'action'])
            ->setRowId('id');
    }

    public function query(PaymentSync $model): QueryBuilder
    {
        $teamId = (int) auth()->user()->currentTeam->id;

        return $model->newQuery()
            ->where('team_id', $teamId)
            ->where('provider', 'mercadopago')
            ->where('status', 'approved')
            ->with([
                'bankStatementLine' => function ($query) use ($teamId): void
                {
                    $query->whereHas('statement', function ($statement) use ($teamId): void
                    {
                        $statement->where('team_id', $teamId)
                            ->where('provider', 'mercadopago');
                    });
                },
            ])
            ->orderByDesc('charge_created_at')
            ->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('payment-reconcile-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(5, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
            ]);
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::make('occurred_at_label')
                ->title(__('payment_sync.reconcile.columns.date'))
                ->orderable(false)
                ->searchable(false),
            Column::make('amount_label')
                ->title(__('payment_sync.reconcile.columns.amount'))
                ->orderable(false)
                ->searchable(true),
            Column::make('statement_payer')
                ->title(__('payment_sync.reconcile.columns.statement_payer'))
                ->orderable(false)
                ->searchable(true),
            Column::make('humano_client')
                ->title(__('payment_sync.reconcile.columns.humano_client'))
                ->orderable(false)
                ->searchable(true),
            Column::make('invoice_label')
                ->title(__('payment_sync.reconcile.columns.invoice'))
                ->orderable(false)
                ->searchable(true),
            Column::make('external_id')
                ->title(__('payment_sync.mercadopago.columns.external_id'))
                ->orderable(true)
                ->searchable(true),
            Column::make('reconcile_status')
                ->title(__('payment_sync.reconcile.columns.status'))
                ->orderable(false)
                ->searchable(false),
            Column::computed('action')
                ->title(__('payment_sync.mercadopago.columns.actions'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('text-center'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function suggestionsBySyncId(): array
    {
        if ($this->suggestionsBySyncId !== null)
        {
            return $this->suggestionsBySyncId;
        }

        $teamId = (int) auth()->user()->currentTeam->id;
        $force = request()->boolean('rebuild');

        $cacheKey = 'payment_reconcile.suggestions.'.$teamId;
        if ($force)
        {
            Cache::forget($cacheKey);
        }

        $items = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($teamId): array
        {
            return app(PaymentReconcileQueueService::class)->buildQueue($teamId);
        });

        $this->suggestionsBySyncId = collect($items)
            ->keyBy(fn (array $item) => (int) ($item['sync_id'] ?? 0))
            ->all();

        return $this->suggestionsBySyncId;
    }

    private function statementLineFor(PaymentSync $sync): ?BankStatementLine
    {
        if ($sync->relationLoaded('bankStatementLine'))
        {
            $line = $sync->getRelation('bankStatementLine');

            return $line instanceof BankStatementLine ? $line : null;
        }

        return $sync->bankStatementLine()
            ->whereHas('statement', function ($query) use ($sync): void
            {
                $query->where('team_id', $sync->team_id)
                    ->where('provider', 'mercadopago');
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $suggestion
     */
    private function statusKey(PaymentSync $sync, ?array $suggestion): string
    {
        $payment = $sync->importedMercadoPagoPayment();
        if ($payment instanceof Payment)
        {
            if ($sync->isReconcileDismissed())
            {
                return 'confirmed';
            }

            if (is_array($suggestion) && ($suggestion['kind'] ?? '') === 'mismatch')
            {
                return 'mismatch';
            }

            $line = $this->statementLineFor($sync);
            $payerName = $line?->payer_name ?: $sync->settlementPayerName();
            $enterpriseName = (string) ($payment->enterprise?->name ?? '');
            if (filled($payerName) && filled($enterpriseName) && ! $this->namesCompatible($payerName, $enterpriseName))
            {
                return 'mismatch';
            }

            return 'matched';
        }

        if (is_array($suggestion) && ($suggestion['kind'] ?? '') === 'suggestion')
        {
            return 'suggestion';
        }

        return 'pending';
    }

    /**
     * @param  array<string, mixed>|null  $suggestion
     */
    private function statusBadge(PaymentSync $sync, ?array $suggestion): string
    {
        return match ($this->statusKey($sync, $suggestion))
        {
            'matched' => '<span class="badge bg-label-success">'.e(__('payment_sync.reconcile.status.matched')).'</span>',
            'confirmed' => '<span class="badge bg-label-success">'.e(__('payment_sync.reconcile.status.confirmed')).'</span>',
            'mismatch' => '<span class="badge bg-label-warning">'.e(__('payment_sync.reconcile.status.mismatch')).'</span>',
            'suggestion' => '<span class="badge bg-label-primary">'.e(__('payment_sync.reconcile.status.suggestion')).'</span>',
            default => '<span class="badge bg-label-secondary">'.e(__('payment_sync.reconcile.status.pending')).'</span>',
        };
    }

    private function namesCompatible(string $payerName, string $enterpriseName): bool
    {
        $normalize = function (string $name): string
        {
            $normalized = mb_strtolower(trim($name));
            $normalized = str_replace(['.', ',', ';'], ' ', $normalized);
            $normalized = preg_replace('/\b(s\.?\s*a\.?|s\.?\s*r\.?\s*l\.?|sa|srl|ltda|llc|inc)\b/u', ' ', $normalized) ?? $normalized;
            $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

            return trim($normalized);
        };

        $left = $normalize($payerName);
        $right = $normalize($enterpriseName);
        if ($left === '' || $right === '')
        {
            return false;
        }

        return $left === $right
            || str_contains($left, $right)
            || str_contains($right, $left);
    }

    private function applyStatementPayerSearch($query, string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '')
        {
            return;
        }

        $like = '%'.mb_strtolower($keyword).'%';

        $query->where(function ($outer) use ($like): void
        {
            $outer->whereRaw(
                "LOWER(COALESCE(payment_syncs.raw_payload->'settlement_payer'->>'name', '')) LIKE ?",
                [$like],
            )->orWhereRaw(
                "LOWER(COALESCE(payment_syncs.raw_payload->'settlement_payer'->>'id_number', '')) LIKE ?",
                [$like],
            )->orWhere('payment_syncs.customer_email', 'like', $like)
                ->orWhere('payment_syncs.description', 'like', $like)
                ->orWhereExists(function ($sub) use ($like): void
                {
                    $sub->selectRaw('1')
                        ->from('bank_statement_lines')
                        ->join('bank_statements', 'bank_statements.id', '=', 'bank_statement_lines.bank_statement_id')
                        ->whereColumn('bank_statement_lines.external_id', 'payment_syncs.external_id')
                        ->whereColumn('bank_statements.team_id', 'payment_syncs.team_id')
                        ->where('bank_statements.provider', 'mercadopago')
                        ->where(function ($payer) use ($like): void
                        {
                            $payer->whereRaw('LOWER(COALESCE(bank_statement_lines.payer_name, \'\')) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(bank_statement_lines.payer_id_number, \'\')) LIKE ?', [$like]);
                        });
                });
        });
    }

    private function applyHumanoClientSearch($query, string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '')
        {
            return;
        }

        $like = '%'.mb_strtolower($keyword).'%';

        $query->whereExists(function ($sub) use ($like): void
        {
            $sub->selectRaw('1')
                ->from('payments')
                ->join('enterprises', 'enterprises.id', '=', 'payments.enterprise_id')
                ->whereColumn('payments.team_id', 'payment_syncs.team_id')
                ->where('payments.source_provider', 'mercadopago')
                ->where(function ($match): void
                {
                    $match->whereColumn('payments.source_reference_id', 'payment_syncs.external_id')
                        ->orWhereRaw("payments.source_reference_id LIKE payment_syncs.external_id || ':%'");
                })
                ->whereRaw('LOWER(COALESCE(enterprises.name, \'\')) LIKE ?', [$like]);
        });
    }

    private function applyInvoiceSearch($query, string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '')
        {
            return;
        }

        $like = '%'.mb_strtolower($keyword).'%';

        $query->whereExists(function ($sub) use ($like): void
        {
            $sub->selectRaw('1')
                ->from('payments')
                ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                ->whereColumn('payments.team_id', 'payment_syncs.team_id')
                ->where('payments.source_provider', 'mercadopago')
                ->where(function ($match): void
                {
                    $match->whereColumn('payments.source_reference_id', 'payment_syncs.external_id')
                        ->orWhereRaw("payments.source_reference_id LIKE payment_syncs.external_id || ':%'");
                })
                ->whereRaw('LOWER(COALESCE(invoices.number, \'\')) LIKE ?', [$like]);
        });
    }

    private function applyAmountSearch($query, string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '')
        {
            return;
        }

        $digits = preg_replace('/\D+/', '', $keyword) ?? '';
        $like = '%'.mb_strtolower($keyword).'%';

        $query->where(function ($outer) use ($like, $digits): void
        {
            $outer->where('payment_syncs.currency', 'like', $like);

            if ($digits !== '')
            {
                $outer->orWhere('payment_syncs.amount_net_cents', 'like', '%'.$digits.'%')
                    ->orWhere('payment_syncs.external_id', 'like', '%'.$digits.'%');
            }
        });
    }
}
