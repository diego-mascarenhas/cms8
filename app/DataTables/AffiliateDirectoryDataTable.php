<?php

namespace App\DataTables;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AffiliateDirectoryDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', function (Team $team): string
            {
                return '<a href="'.e(route('affiliate.show', $team)).'" class="fw-medium">'.e($team->name).'</a>';
            })
            ->addColumn('owner', function (Team $team): string
            {
                return e($team->owner?->email ?? '—');
            })
            ->editColumn('stripe_id', function (Team $team): string
            {
                return e((string) $team->stripe_id);
            })
            ->addColumn('referrals_count', function (Team $team): string
            {
                return (string) (int) ($team->referrals_count ?? 0);
            })
            ->orderColumn('referrals_count', function (QueryBuilder $query, string $order): void
            {
                $query->orderBy('referrals_count', $order);
            })
            ->addColumn('commission', function (Team $team): string
            {
                return number_format(((int) ($team->commission_cents ?? 0)) / 100, 2, ',', '.');
            })
            ->addColumn('action', function (Team $team): string
            {
                return view('affiliate.action', ['team' => $team])->render();
            })
            ->rawColumns(['name', 'action'])
            ->setRowId('id');
    }

    public function query(Team $model): QueryBuilder
    {
        $referredCount = DB::table('teams as referred_teams')
            ->selectRaw('count(*)')
            ->whereColumn('referred_teams.referred_by', 'teams.stripe_id')
            ->where('referred_teams.referred_by', '!=', '');

        return $model->newQuery()
            ->select('teams.*')
            ->selectSub($referredCount, 'referrals_count')
            ->with('owner')
            ->withSum('billingAffiliateCommissionsAsReferrer as commission_cents', 'commission_amount_cents')
            ->whereNotNull('teams.stripe_id')
            ->where('teams.stripe_id', '!=', '')
            ->where(function (QueryBuilder $query): void
            {
                $query->whereExists(function ($sub): void
                {
                    $sub->selectRaw('1')
                        ->from('teams as referred_teams')
                        ->whereColumn('referred_teams.referred_by', 'teams.stripe_id')
                        ->where('referred_teams.referred_by', '!=', '');
                })->orWhereExists(function ($sub): void
                {
                    $sub->selectRaw('1')
                        ->from('subscriptions')
                        ->whereColumn('subscriptions.referred_by', 'teams.stripe_id');
                })->orWhereExists(function ($sub): void
                {
                    $sub->selectRaw('1')
                        ->from('billing_affiliate_commissions')
                        ->whereColumn('billing_affiliate_commissions.referrer_team_id', 'teams.id');
                });
            });
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('affiliates-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0, 'asc')
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

    /**
     * @return array<int, Column>
     */
    protected function getColumns(): array
    {
        return [
            Column::make('name')->title('Afiliado')->searchable(true)->orderable(true),
            Column::computed('owner')->title('Email')->searchable(false)->orderable(false),
            Column::make('stripe_id')->title('Código')->searchable(true)->orderable(true),
            Column::computed('referrals_count')->title('Referidos')->searchable(false)->orderable(true)->addClass('text-center'),
            Column::computed('commission')->title('Comisión')->searchable(false)->orderable(false)->addClass('text-end'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->addClass('text-center')
                ->title('Acciones'),
        ];
    }

    protected function filename(): string
    {
        return 'Affiliates_'.date('YmdHis');
    }
}
