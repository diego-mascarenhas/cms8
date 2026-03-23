<?php

namespace App\DataTables;

use App\Models\StripeSubscription;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class StripeSubscriptionDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->setRowId('id')
            ->rawColumns(['customer_email', 'status', 'amount'])
            ->editColumn('customer_name', function (StripeSubscription $sub)
            {
                return $sub->customer_name ?: '—';
            })
            ->editColumn('customer_email', function (StripeSubscription $sub)
            {
                if (! $sub->customer_email)
                {
                    return '—';
                }

                return '<a href="mailto:'.e($sub->customer_email).'">'.e($sub->customer_email).'</a>';
            })
            ->editColumn('plan_name', function (StripeSubscription $sub)
            {
                return $sub->plan_name ?: $sub->stripe_id;
            })
            ->editColumn('status', function (StripeSubscription $sub)
            {
                $badge = match ($sub->status)
                {
                    'active' => 'success',
                    'trialing' => 'info',
                    default => 'secondary',
                };

                return '<span class="badge bg-'.$badge.'">'.e($sub->status ?: '—').'</span>';
            })
            ->editColumn('amount_total', function (StripeSubscription $sub)
            {
                if ($sub->amount_total === null)
                {
                    return '—';
                }

                $currency = strtoupper($sub->price_currency ?? 'EUR');

                return number_format((float) $sub->amount_total, 2, ',', '.').' '.$currency;
            })
            ->editColumn('current_period_end', function (StripeSubscription $sub)
            {
                return $sub->current_period_end
                    ? $sub->current_period_end->format('d/m/Y')
                    : '—';
            });
    }

    public function query(StripeSubscription $model): QueryBuilder
    {
        $teamId = auth()->user()->currentTeam?->id;

        return $model
            ->newQuery()
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->when(! $teamId, fn ($q) => $q->whereRaw('1 = 0'));
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('stripe-subscription-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
                    $("#stripe-subscription-table tbody tr").css({
                        "user-select": "none",
                        "-webkit-user-select": "none",
                        "-moz-user-select": "none",
                        "-ms-user-select": "none"
                    });
                }',
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('customer_name')->title(__('Cliente'))->addClass('all')->searchable(true)->orderable(true),
            Column::make('customer_email')->title(__('Email'))->addClass('min-tablet')->searchable(true)->orderable(true),
            Column::make('plan_name')->title(__('Plan'))->addClass('min-tablet')->searchable(true)->orderable(true),
            Column::make('status')->title(__('Estado'))->addClass('min-phone')->className('text-center')->orderable(true),
            Column::make('amount_total')->title(__('Importe'))->addClass('min-desktop')->className('text-end')->orderable(true),
            Column::make('current_period_end')->title(__('Próximo periodo'))->addClass('min-desktop')->className('text-center')->orderable(true),
        ];
    }

    protected function filename(): string
    {
        return 'StripeSubscription_'.date('YmdHis');
    }
}
