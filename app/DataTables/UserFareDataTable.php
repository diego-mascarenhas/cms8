<?php

namespace App\DataTables;

use App\Models\UserFare;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UserFareDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($userFare)
            {
                return view('user-fare.action', compact('userFare'));
            })
            ->addColumn('languages', function ($userFare)
            {
                return '<span class="badge bg-label-primary me-1">'.
                    ($userFare->languageOrigin ? $userFare->languageOrigin->name : 'N/A').
                    ' → '.
                    ($userFare->languageDestination ? $userFare->languageDestination->name : 'N/A').
                    '</span>';
            })
            ->addColumn('fare_type', function ($userFare)
            {
                return $userFare->fare ? $userFare->fare->name.' ('.($userFare->fare->unit->type ?? 'N/A').')' : 'N/A';
            })
            ->addColumn('price', function ($userFare)
            {
                $negotiableTag = $userFare->negotiable ? '<span class="badge bg-label-warning ms-1">Negociable</span>' : '';

                return $userFare->formatted_amount.' / '.($userFare->fare->unit->type ?? 'N/A').$negotiableTag;
            })
            ->addColumn('user', function ($userFare)
            {
                return $userFare->user ? $userFare->user->name : 'N/A';
            })
            ->rawColumns(['action', 'languages', 'price', 'fare_type'])
            ->setRowId('id');
    }

    public function query(UserFare $model): QueryBuilder
    {
        // Solo mostrar tarifas del usuario actual
        return $model->newQuery()
            ->where('user_id', Auth::id())
            ->with(['languageOrigin', 'languageDestination', 'fare.unit', 'currency', 'user']);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('user-fares-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Bfrtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->buttons([
                'copy', 'excel', 'pdf', 'print',
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('ID')->addClass('text-center'),
            Column::make('fare_type')->title('Tipo de Tarifa')->searchable(false),
            Column::make('languages')->title('Idiomas')->searchable(false),
            Column::make('price')->title('Precio')->searchable(false),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title('Acciones'),
        ];
    }

    protected function filename(): string
    {
        return 'UserFares_'.date('YmdHis');
    }
}
