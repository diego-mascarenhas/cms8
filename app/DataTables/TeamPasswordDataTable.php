<?php

namespace App\DataTables;

use App\Models\TeamPassword;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TeamPasswordDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (TeamPassword $teamPassword)
            {
                return view('password.action', ['teamPassword' => $teamPassword])->render();
            })
            ->editColumn('name', function (TeamPassword $teamPassword)
            {
                return e($teamPassword->name);
            })
            ->editColumn('username', function (TeamPassword $teamPassword)
            {
                return $teamPassword->username ? e($teamPassword->username) : '<span class="text-muted">—</span>';
            })
            ->editColumn('enterprise_id', function (TeamPassword $teamPassword)
            {
                return $teamPassword->enterprise
                    ? e($teamPassword->enterprise->name)
                    : '<span class="text-muted">—</span>';
            })
            ->editColumn('updated_at', function (TeamPassword $teamPassword)
            {
                return Carbon::parse($teamPassword->updated_at)->format('d-m-Y H:i');
            })
            ->rawColumns(['username', 'enterprise_id', 'action'])
            ->setRowId('id');
    }

    public function query(TeamPassword $model): QueryBuilder
    {
        $query = $model->newQuery()->with('enterprise');

        $request = $this->request();
        if ($request->filled('enterprise_id'))
        {
            $query->where('enterprise_id', (int) $request->get('enterprise_id'));
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('team-passwords-table')
            ->columns($this->getColumns())
            ->minifiedAjax('', "data.enterprise_id = $('#filter_password_enterprise_id').val() || '';")
            ->dom('frtip')
            ->orderBy(3, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'select' => false,
                'lengthChange' => false,
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('name')->title(__('Name')),
            Column::make('username')->title(__('Username')),
            Column::make('enterprise_id')->title(__('Enterprise')),
            Column::make('updated_at')->title(__('Updated')),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center')
                ->title(__('Actions')),
        ];
    }

    protected function filename(): string
    {
        return 'TeamPasswords_'.date('YmdHis');
    }
}
