<?php

namespace App\DataTables;

use App\Helpers\TokenHelper;
use App\Models\Account;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccountDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', function ($account)
            {
                return view('account.name-cell', ['account' => $account])->render();
            })
            ->filterColumn('name', function ($query, $keyword)
            {
                $query->whereResponsibleMatches($keyword);
            })
            ->addColumn('owner_name', function ($account)
            {
                return view('account.owner-cell', ['owner' => $account->owner])->render();
            })
            ->filterColumn('owner_name', function ($query, $keyword)
            {
                $query->whereResponsibleMatches($keyword);
            })
            ->addColumn('active_clients_count', function ($account)
            {
                return $account->active_clients_count;
            })
            ->addColumn('subscriptions_count', function ($account)
            {
                return $account->subscriptions_count;
            })
            ->addColumn('action', function ($account)
            {
                $autologinButtons = '';
                if ($account->owner)
                {
                    $token = TokenHelper::generateSignedToken($account->owner, 'account_owner_autologin', 720); // 30 days
                    $loginUrl = route('login.token', ['token' => $token]);
                    $fullUrl = url($loginUrl);

                    $autologinButtons = '<a href="javascript:;"
                                           class="text-body"
                                           onclick="copyAutologinLink(\''.addslashes($fullUrl).'\', this)"
                                           title="Copiar link de autologueo">
                                            <i class="ti ti-link ti-sm me-2"></i>
                                        </a>
                                        <a href="javascript:;"
                                           class="text-info"
                                           onclick="sendAutologinInvitation('.$account->id.', this)"
                                           title="Enviar invitación por email">
                                            <i class="ti ti-send ti-sm me-2"></i>
                                        </a>
                                        <a href="javascript:;"
                                           class="text-body"
                                           onclick="changeAccountPassword('.$account->id.')"
                                           title="Cambiar contraseña">
                                            <i class="ti ti-key ti-sm me-2"></i>
                                        </a>
                                        <a href="javascript:;"
                                           class="text-danger"
                                           onclick="revokeAutologinToken('.$account->id.', this)"
                                           title="Revocar tokens de autologueo">
                                            <i class="ti ti-x ti-sm me-2"></i>
                                        </a>';
                }

                return '<div class="d-flex justify-content-center align-items-center">
					<a href="'.route('account.subscriptions', $account->id).'" class="text-body" title="Ver suscripciones">
						<i class="ti ti-eye ti-sm me-2"></i>
					</a>
					'.$autologinButtons.'
					<a href="'.route('account.edit', $account->id).'" class="text-body" title="Editar">
						<i class="ti ti-edit ti-sm"></i>
					</a>
				</div>';
            })
            ->setRowId('id')
            ->rawColumns(['name', 'owner_name', 'action']);
    }

    public function query(Account $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'owner:id,name,email,phone',
                'subscriptions',
                'billingEnterprise.contacts' => function ($query)
                {
                    $query->withoutGlobalScopes();
                },
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('account-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, direction: 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'autoWidth' => false,
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden()->searchable(false),
            Column::make('name')
                ->title(__('Responsable'))
                ->addClass('all')
                ->width('280px')
                ->orderable(true)
                ->searchable(true),
            Column::computed('owner_name')
                ->title(__('Contacto'))
                ->className('text-start')
                ->addClass('min-phone')
                ->width('220px')
                ->orderable(true)
                ->searchable(true),
            Column::computed('active_clients_count')
                ->title('Clientes')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true),
            Column::computed('subscriptions_count')
                ->title('Suscripciones')
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(true),
            Column::computed('action')
                ->title('Acciones')
                ->className('text-center')
                ->addClass('all')
                ->orderable(false)
                ->searchable(false),
        ];
    }

    protected function filename(): string
    {
        return 'Account_'.date('YmdHis');
    }
}
