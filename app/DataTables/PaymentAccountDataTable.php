<?php

namespace App\DataTables;

use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PaymentAccountDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (PaymentAccount $account)
            {
                return view('payment-account.action', compact('account'))->render();
            })
            ->setRowId('id')
            ->rawColumns(['action', 'status', 'currency_code', 'payment_types'])
            ->addColumn('currency_code', function (PaymentAccount $account)
            {
                $code = strtoupper((string) ($account->currency?->code ?? ''));

                return $code !== ''
                    ? '<span class="badge bg-label-primary">'.e($code).'</span>'
                    : '<span class="text-muted">—</span>';
            })
            ->filterColumn('currency_code', function ($query, $keyword)
            {
                $query->whereHas('currency', function ($currencyQuery) use ($keyword)
                {
                    $currencyQuery->whereRaw('code LIKE ?', ['%'.strtoupper($keyword).'%']);
                });
            })
            ->addColumn('payment_types', function (PaymentAccount $account)
            {
                if ($account->paymentTypes->isEmpty())
                {
                    return '<span class="text-muted">'.e(__('Todas las formas activas')).'</span>';
                }

                return e($account->paymentTypes->map(fn ($type) => $type->display_name)->join(', '));
            })
            ->filterColumn('payment_types', function ($query, $keyword)
            {
                $query->whereHas('paymentTypes', function ($typeQuery) use ($keyword)
                {
                    $typeQuery->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->editColumn('status', function (PaymentAccount $account)
            {
                if ((int) $account->status === 1)
                {
                    return '<span class="badge bg-label-success">'.e(__('Activa')).'</span>';
                }

                return '<span class="badge bg-label-secondary">'.e(__('Inactiva')).'</span>';
            });
    }

    public function query(PaymentAccount $model): QueryBuilder
    {
        return $model
            ->newQuery()
            ->withoutGlobalScope('activeStatus')
            ->with(['currency', 'paymentTypes']);
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('payment-account-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(5, 'desc')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'pageLength' => 25,
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Name'))
                ->addClass('all'),
            Column::make('code')
                ->title(__('Code'))
                ->addClass('min-tablet'),
            Column::computed('currency_code')
                ->title(__('Currency'))
                ->addClass('min-phone')
                ->className('text-center')
                ->orderable(false)
                ->searchable(true),
            Column::computed('payment_types')
                ->title(__('Formas de pago aceptadas'))
                ->addClass('min-desktop')
                ->orderable(false)
                ->searchable(true),
            Column::make('status')
                ->title(__('Status'))
                ->addClass('min-phone')
                ->className('text-center'),
            Column::computed('action')
                ->title(__('Actions'))
                ->addClass('min-desktop')
                ->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30),
        ];
    }

    protected function filename(): string
    {
        return 'PaymentAccount_'.date('YmdHis');
    }
}
