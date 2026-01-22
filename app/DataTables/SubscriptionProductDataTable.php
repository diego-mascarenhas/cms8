<?php

namespace App\DataTables;

use App\Models\SubscriptionProduct;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SubscriptionProductDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('active', function ($product)
            {
                return $product->active
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>';
            })
            ->editColumn('unit_amount', function ($product)
            {
                return $product->getFormattedPrice();
            })
            ->addColumn('billing_frequency', function ($product)
            {
                return $product->getBillingFrequency() ?? '—';
            })
            ->editColumn('category', function ($product)
            {
                return $product->category ?? '—';
            })
            ->editColumn('plan', function ($product)
            {
                return $product->plan ?? '—';
            })
            ->editColumn('type', function ($product)
            {
                return $product->type ?? '—';
            })
            ->addColumn('action', function ($product)
            {
                $html = '<div class="d-flex justify-content-center align-items-center">';
                if (auth()->user()->hasRole('root')) {
                    $html .= '<a href="'.route('account.products.edit', $product->id).'" class="text-body" title="Editar producto">
                        <i class="ti ti-edit ti-sm me-2"></i>
                    </a>';
                }
                // SLA actions
                if ($product->sla) {
                    $html .= '<a href="'.route('sla.edit', ['productId' => $product->id, 'slaId' => $product->sla->id]).'" class="text-body" title="Editar SLA">
                        <i class="ti ti-file-text ti-sm me-2"></i>
                    </a>';
                } else {
                    $html .= '<a href="'.route('sla.create', $product->id).'" class="text-body" title="Crear SLA">
                        <i class="ti ti-file-plus ti-sm me-2"></i>
                    </a>';
                }
                // Send SLA button
                if ($product->sla) {
                    $html .= '<a href="javascript:;" class="text-body send-sla-btn" data-product-id="'.$product->id.'" title="Enviar SLA">
                        <i class="ti ti-send ti-sm me-2"></i>
                    </a>';
                }
                $html .= '</div>';
                return $html;
            })
            ->setRowId('id')
            ->rawColumns(['active', 'action']);
    }

    public function query(SubscriptionProduct $model): QueryBuilder
    {
        return $model->newQuery()->with('sla')->orderBy('name', 'asc');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('subscription-product-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, direction: 'asc')
            ->responsive(true)
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
                ->title('Nombre')
                ->addClass('all')
                ->orderable(true)
                ->searchable(true),
            Column::make('category')
                ->title('Categoría')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true)
                ->searchable(true),
            Column::make('plan')
                ->title('Plan')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true)
                ->searchable(true),
            Column::make('type')
                ->title('Tipo')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true)
                ->searchable(true),
            Column::make('unit_amount')
                ->title('Precio')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true),
            Column::computed('billing_frequency')
                ->title('Frecuencia')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(false),
            Column::computed('active')
                ->title('Estado')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true),
            Column::computed('action')
                ->title('Acciones')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(false)
                ->searchable(false),
        ];
    }

    protected function filename(): string
    {
        return 'SubscriptionProduct_'.date('YmdHis');
    }
}
