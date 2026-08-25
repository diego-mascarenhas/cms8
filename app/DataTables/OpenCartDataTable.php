<?php

namespace App\DataTables;

use App\Services\OpenCartListingService;
use Illuminate\Support\Collection;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class OpenCartDataTable extends DataTable
{
    public function dataTable(Collection $query): CollectionDataTable
    {
        return (new CollectionDataTable($query))
            ->addColumn('action', function (array $cart)
            {
                return view('order.carts-action', ['cart' => $cart]);
            })
            ->editColumn('total', function (array $cart)
            {
                return '$'.number_format((float) $cart['total'], 2);
            })
            ->rawColumns(['action']);
    }

    public function query(OpenCartListingService $listing): Collection
    {
        $teamId = (int) (auth()->user()?->currentTeam?->id ?? 0);

        return $listing->forTeam($teamId);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('open-cart-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(4, 'desc')
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'dom' => '<"card-header d-flex border-top rounded-0 flex-wrap py-2"<"me-5 ms-n2 pe-5"f><"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center mb-3 mb-sm-0"lB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                'buttons' => [],
                'responsive' => true,
                'select' => false,
                'autoWidth' => false,
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('customer')->title(__('Cliente'))->addClass('text-nowrap'),
            Column::make('channel')->title(__('Canal'))->addClass('text-nowrap'),
            Column::make('items_label')->title(__('Productos')),
            Column::make('quantity')->title(__('Items'))->addClass('text-center'),
            Column::make('updated_at')->title(__('Actualizado'))->addClass('text-nowrap'),
            Column::make('total')->title(__('Total'))->addClass('text-end'),
            Column::computed('action')
                ->title(__('Acciones'))
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->width(80),
        ];
    }

    protected function filename(): string
    {
        return 'OpenCart_'.date('YmdHis');
    }
}
