<?php

namespace App\DataTables;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProductDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('status', function ($product)
            {
                return $product->status
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>';
            })
            ->editColumn('price', function ($product)
            {
                $currency = $product->currency ? $product->currency->code : 'USD';

                return number_format($product->price, 2, ',', '.').' '.$currency;
            })
            ->editColumn('category.name', function ($product)
            {
                return $product->category ? $product->category->name : '—';
            })
            ->addColumn('action', function ($product)
            {
                $html = '<div class="d-flex justify-content-center align-items-center">';
                if (auth()->user()->can('product.show'))
                {
                    $html .= '<a href="'.route('product.show', $product->id).'" class="text-body">
                        <i class="ti ti-edit ti-sm me-2"></i>
                    </a>';
                }
                $html .= '</div>';

                return $html;
            })
            ->setRowId('id')
            ->rawColumns(['status', 'action']);
    }

    public function query(Product $model): QueryBuilder
    {
        return $model->newQuery()->with(['category', 'currency']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('product-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, direction: 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'pageLength' => 60,
                'paging' => false,
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Name'))
                ->addClass('all')
                ->orderable(true)
                ->searchable(true),
            Column::make('category.name')
                ->title(__('Category'))
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true)
                ->searchable(true),
            Column::make('price')
                ->title(__('Price'))
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true),
            Column::computed('status')
                ->title(__('Status'))
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true),
            Column::computed('action')
                ->title(__('Actions'))
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(false)
                ->searchable(false),
        ];
    }

    protected function filename(): string
    {
        return 'Product_'.date('YmdHis');
    }
}
