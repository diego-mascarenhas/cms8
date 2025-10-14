<?php

namespace App\DataTables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UserDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($user)
            {
                return view('user.action', ['id' => $user->id])->render();
            })
            ->setRowId('id')
            ->editColumn('email_verified_at', function ($user)
            {
                if ($user->email_verified_at)
                {
                    return '<span class="badge bg-label-success">'.__('Verified').'</span>';
                } else
                {
                    return '<span class="badge bg-label-warning">'.__('Pending').'</span>';
                }
            })
            ->editColumn('roles', function ($user)
            {
                $roleNames = $user->roles->pluck('name')->toArray();
                $badges = array_map(function ($role)
                {
                    $colorClass = $this->getRoleColorClass($role);

                    return '<span class="badge bg-label-'.$colorClass.'">'.ucfirst($role).'</span>';
                }, $roleNames);

                return implode(' ', $badges);
            })
            ->filterColumn('roles', function ($query, $keyword)
            {
                $query->whereHas('roles', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['email_verified_at', 'roles', 'action']);
    }

    public function query(): QueryBuilder
    {
        // Filter all users by current team
        return User::query()
            ->with('roles')
            ->whereHas('teams', function ($query)
            {
                $query->where('team_id', Auth::user()->currentTeam->id);
            });
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('user-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language([
                'url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json',
            ])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
					$("#user-table tbody tr").css({
						"user-select": "none",
						"-webkit-user-select": "none",
						"-moz-user-select": "none",
						"-ms-user-select": "none"
					});
				}',
            ]);
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Name'))
                ->addClass('all'),
            Column::make('email')
                ->title(__('Email'))
                ->addClass('min-tablet'),
            Column::make('roles')
                ->title(__('Roles'))
                ->className('text-center')
                ->addClass('min-tablet')
                ->orderable(false),
            Column::make('email_verified_at')
                ->title(__('Verified'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false),
            Column::computed('action')
                ->title(__('Actions'))
                ->width(20)
                ->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(30)
                ->addClass('min-desktop'),
        ];
    }

    protected function filename(): string
    {
        return 'User_'.date('YmdHis');
    }

    private function getRoleColorClass($role): string
    {
        $colors = [
            'admin' => 'primary',
            'manager' => 'info',
            'collaborator' => 'success',
            'client' => 'warning',
            'user' => 'secondary',
            'guest' => 'dark',
        ];

        return $colors[strtolower($role)] ?? 'secondary';
    }
}
