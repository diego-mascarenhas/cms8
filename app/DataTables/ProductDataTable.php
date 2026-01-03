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
     * @param  QueryBuilder<Product>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $table = (new EloquentDataTable($query));

        // Add action column
        $table = $table->addColumn('action', function ($product)
        {
            return view('product.action', compact('product'));
        });

        return $table
            ->setRowId('id')
            ->editColumn('name', function ($row)
            {
                $image = $row->image ?? asset('assets/img/ecommerce-images/product-1.png');
                $name = e($row->name);
                $category = $row->category ? e($row->category->name) : '';

                return '<div class="d-flex justify-content-start align-items-center product-name">
                            <div class="avatar-wrapper">
                                <div class="avatar avatar me-2 rounded-2 bg-label-secondary">
                                    <img src="'.$image.'" alt="'.$name.'" class="rounded-2">
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <h6 class="text-body text-nowrap mb-0">'.$name.'</h6>
                                <small class="text-muted text-truncate d-none d-sm-block">'.$category.'</small>
                            </div>
                        </div>';
            })
            ->editColumn('category_id', function ($row)
            {
                return $row->category ? '<span class="badge bg-label-info">'.$row->category->name.'</span>' : '-';
            })
            ->filterColumn('category_id', function ($query, $keyword)
            {
                $query->whereHas('category', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('price', function ($row)
            {
                $currency = $row->currency ? $row->currency->symbol : '$';

                return $currency.number_format($row->price, 2);
            })
            ->editColumn('status', function ($row)
            {
                if ($row->status)
                {
                    return '<span class="badge bg-label-success">Activo</span>';
                } else
                {
                    return '<span class="badge bg-label-secondary">Inactivo</span>';
                }
            })
            ->editColumn('whatsapp_enabled', function ($row)
            {
                if ($row->whatsapp_enabled)
                {
                    return '<i class="ti ti-check text-success ti-sm"></i>';
                } else
                {
                    return '<i class="ti ti-x text-muted ti-sm"></i>';
                }
            })
            ->rawColumns(['action', 'name', 'category_id', 'status', 'whatsapp_enabled']);
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Product>
     */
    public function query(Product $model): QueryBuilder
    {
        return $model->newQuery()->with(['category', 'currency']);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('product-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(1, 'asc')
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
            Column::make('id')->title('ID')->addClass('text-nowrap'),
            Column::make('name')->title(__('Producto'))->addClass('text-nowrap'),
            Column::make('category_id')->title(__('Categoría'))->addClass('text-center'),
            Column::make('price')->title(__('Precio'))->addClass('text-end'),
            Column::make('status')->title(__('Estado'))->addClass('text-center'),
            Column::make('whatsapp_enabled')->title('WhatsApp')->addClass('text-center')->width(80),
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
        return 'Product_'.date('YmdHis');
    }
}
