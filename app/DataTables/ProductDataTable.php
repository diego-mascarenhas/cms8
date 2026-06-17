<?php

namespace App\DataTables;

use App\Enums\ProductCatalogStatus;
use App\Models\Product;
use App\Support\DataTableFormatter;
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
            ->editColumn('name', function ($product)
            {
                return DataTableFormatter::showLink($product, 'product.show', $product->name, 'view', [$product->id]);
            })
            ->editColumn('status', function ($product)
            {
                $catalog = $product->catalog_status;
                if (! $catalog instanceof ProductCatalogStatus)
                {
                    $catalog = $product->status
                        ? ProductCatalogStatus::Publish
                        : ProductCatalogStatus::Draft;
                }
                $class = match ($catalog)
                {
                    ProductCatalogStatus::Publish => 'bg-success',
                    ProductCatalogStatus::Draft => 'bg-secondary',
                    ProductCatalogStatus::Pending => 'bg-warning',
                    ProductCatalogStatus::Private => 'bg-info',
                };

                return '<span class="badge '.$class.'">'.e($catalog->label()).'</span>';
            })
            ->editColumn('price', function ($product)
            {
                $currency = $product->currency ? $product->currency->code : 'ARS';
                $regular = number_format((float) $product->price, 2, ',', '.');
                $current = number_format($product->currentSellingPrice(), 2, ',', '.');

                if ($product->isOnSale())
                {
                    return '<span class="text-muted text-decoration-line-through">'.$regular.'</span> '
                        .$current.' '.e($currency);
                }

                return $current.' '.e($currency);
            })
            ->editColumn('store.name', function ($product)
            {
                return $product->store ? $product->store->name : '—';
            })
            ->editColumn('category.name', function ($product)
            {
                return $product->category ? $product->category->name : '—';
            })
            ->rawColumns(['name', 'status', 'price', 'store.name', 'category.name', 'action'])
            ->addColumn('action', function ($product)
            {
                $user = auth()->user();
                $html = '<div class="d-flex justify-content-center align-items-center">';

                if ($user && $user->can('view', $product))
                {
                    $html .= '<a href="'.e(route('product.show', $product->id)).'" class="text-body" title="'.e(__('View')).'">'
                        .'<i class="ti ti-eye ti-sm me-2"></i></a>';
                }
                if ($user && $user->can('update', $product))
                {
                    $html .= '<a href="'.e(route('product.edit', $product->id)).'" class="text-body" title="'.e(__('Edit')).'">'
                        .'<i class="ti ti-edit ti-sm me-2"></i></a>';
                }
                if ($user && $user->can('delete', $product))
                {
                    $html .= '<a href="#" class="text-danger" title="'.e(__('Delete')).'" onclick="deleteProduct('.$product->id.'); return false;">'
                        .'<i class="ti ti-trash ti-sm"></i></a>';
                }

                $html .= '</div>';

                return $html;
            })
            ->setRowId('id');
    }

    public function query(Product $model): QueryBuilder
    {
        $query = $model->newQuery()->with(['category', 'currency', 'store']);

        $storeId = request()->get('store_id');
        if (is_numeric($storeId) && (int) $storeId > 0)
        {
            $query->where('store_id', (int) $storeId);
        }

        $categoryId = request()->get('category_id');
        if (is_numeric($categoryId) && (int) $categoryId > 0)
        {
            $query->where('category_id', (int) $categoryId);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('product-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                '',
                "data.store_id = $('#filter_store_id').val(); data.category_id = $('#filter_category_id').val();",
            )
            ->dom('frtip')
            ->orderBy(1, direction: 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'pageLength' => 25,
                'paging' => true,
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
            Column::make('code')
                ->title(__('Code'))
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true)
                ->searchable(true),
            Column::make('store.name')
                ->title(__('Store'))
                ->className('text-center')
                ->addClass('min-phone')
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
