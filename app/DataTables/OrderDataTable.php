<?php

namespace App\DataTables;

use App\Models\Order;
use App\Support\DataTableFormatter;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class OrderDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<Order>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $table = (new EloquentDataTable($query));

        // Add action column
        $table = $table->addColumn('action', function ($order)
        {
            return view('order.action', compact('order'));
        });

        return $table
            ->setRowId('id')
            ->editColumn('order_number', function ($row)
            {
                return DataTableFormatter::showLink(
                    $row,
                    'order.show',
                    '#'.$row->order_number,
                    'view',
                    [$row->id],
                    'fw-medium text-primary',
                );
            })
            ->editColumn('contact_id', function ($row)
            {
                if ($row->contact)
                {
                    $contact = $row->contact;
                    $name = e($contact->name);
                    $avatar = \App\Helpers\AvatarHelper::generate($contact->name, 32);
                    $nameHtml = DataTableFormatter::showLink($contact, 'contact.show', $contact->name, 'view', [$contact->id], 'fw-medium text-primary');

                    return '<div class="d-flex justify-content-start align-items-center">
                                <div class="avatar-wrapper">
                                    <div class="avatar avatar-sm me-2">
                                        <img src="'.$avatar.'" alt="'.$name.'" class="rounded-circle">
                                    </div>
                                </div>
                                <div class="d-flex flex-column justify-content-center">
                                    '.$nameHtml.'
                                </div>
                            </div>';
                }

                return '-';
            })
            ->filterColumn('contact_id', function ($query, $keyword)
            {
                $query->whereHas('contact', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('total_amount', function ($row)
            {
                $currency = $row->currency ? $row->currency->symbol : '$';

                return $currency.number_format($row->total_amount, 2);
            })
            ->editColumn('payment_status', function ($row)
            {
                return '<span class="badge '.$row->payment_status_badge.'">'.$row->payment_status_label.'</span>';
            })
            ->filterColumn('payment_status', function ($query, $keyword)
            {
                $query->where('payment_status', $keyword);
            })
            ->editColumn('delivery_status', function ($row)
            {
                return '<span class="badge '.$row->delivery_status_badge.'">'.$row->delivery_status_label.'</span>';
            })
            ->filterColumn('delivery_status', function ($query, $keyword)
            {
                $query->where('delivery_status', $keyword);
            })
            ->editColumn('created_at', function ($row)
            {
                return $row->created_at->format('d/m/Y H:i');
            })
            ->rawColumns(['action', 'order_number', 'contact_id', 'payment_status', 'delivery_status']);
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Order>
     */
    public function query(Order $model): QueryBuilder
    {
        return $model->newQuery()->with(['contact', 'currency']);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('order-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'desc')
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'dom' => '<"card-header d-flex border-top rounded-0 flex-wrap py-2"<"me-5 ms-n2 pe-5"f><"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center mb-3 mb-sm-0"lB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                'buttons' => [],
                'responsive' => true,
                'select' => false,
                'autoWidth' => false,
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('order_number')->title(__('Orden'))->addClass('text-nowrap'),
            Column::make('created_at')->title(__('Fecha'))->addClass('text-nowrap'),
            Column::make('contact_id')->title(__('Cliente'))->addClass('text-nowrap'),
            Column::make('payment_status')->title(__('Pago'))->addClass('text-center'),
            Column::make('delivery_status')->title(__('Entrega'))->addClass('text-center'),
            Column::make('total_amount')->title(__('Total'))->addClass('text-end'),
            Column::computed('action')
                ->title(__('Acciones'))
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->width(80),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Order_'.date('YmdHis');
    }
}
