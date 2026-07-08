<?php

namespace App\DataTables;

use App\Models\PaidAdCampaign;
use App\Support\DataTableFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PaidAdCampaignDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (PaidAdCampaign $row)
            {
                return view('paid-ads.action', ['campaign' => $row])->render();
            })
            ->setRowId('id')
            ->editColumn('name', function (PaidAdCampaign $row)
            {
                return DataTableFormatter::showLink($row, 'paid-ads.show', $row->name, 'view', [$row->id]);
            })
            ->editColumn('objective', function (PaidAdCampaign $row)
            {
                return e($row->objective?->label() ?? '—');
            })
            ->editColumn('status', function (PaidAdCampaign $row)
            {
                $status = $row->status;

                return '<span class="badge '.e($status->badgeClasses()).'">'.e($status->label()).'</span>';
            })
            ->addColumn('platforms_list', function (PaidAdCampaign $row)
            {
                if ($row->platforms->isEmpty())
                {
                    return '<span class="text-muted">—</span>';
                }

                return $row->platforms
                    ->map(fn ($p) => '<i class="'.e($p->platform->icon()).' ti-sm me-1" title="'.e($p->platform->label()).'"></i>')
                    ->implode('');
            })
            ->addColumn('budget_display', function (PaidAdCampaign $row)
            {
                if ($row->budget_amount === null)
                {
                    return '—';
                }

                $suffix = $row->budget_type === 'daily' ? '/'.__('day') : '';

                return e(number_format((float) $row->budget_amount, 2).' '.$row->currency.$suffix);
            })
            ->editColumn('created_at', function (PaidAdCampaign $row)
            {
                return $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '—';
            })
            ->rawColumns(['action', 'name', 'status', 'platforms_list']);
    }

    public function query(PaidAdCampaign $model): QueryBuilder
    {
        return $model->newQuery()->with(['platforms:id,paid_ad_campaign_id,platform']);
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('paid-ads-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(6, 'desc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json']);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title(__('Name'))->addClass('all'),
            Column::make('objective')->title(__('Objective'))->searchable(false)->orderable(false),
            Column::make('status')->title(__('Status'))->searchable(false),
            Column::computed('platforms_list')->title(__('Platforms'))->orderable(false)->searchable(false),
            Column::computed('budget_display')->title(__('Budget'))->orderable(false)->searchable(false),
            Column::make('created_at')->title(__('Created'))->searchable(false),
            Column::computed('action')
                ->title(__('Actions'))
                ->exportable(false)
                ->printable(false)
                ->width(140)
                ->addClass('text-center'),
        ];
    }
}
