<?php

namespace App\DataTables;

use App\Models\UserDailyPerformanceInsight;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UserDailyPerformanceInsightDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('user_name', function (UserDailyPerformanceInsight $row)
            {
                return e($row->user?->name ?? '—');
            })
            ->editColumn('insight_date', function (UserDailyPerformanceInsight $row)
            {
                return e($row->insight_date?->toDateString() ?? '');
            })
            ->editColumn('performance_ratio', function (UserDailyPerformanceInsight $row)
            {
                return '<span class="fw-medium">'.e(number_format((float) $row->performance_ratio, 2)).'</span>';
            })
            ->editColumn('headline', function (UserDailyPerformanceInsight $row)
            {
                return e($row->headline);
            })
            ->editColumn('focus', function (UserDailyPerformanceInsight $row)
            {
                return e(Str::limit(strip_tags((string) $row->focus), 80));
            })
            ->editColumn('message', function (UserDailyPerformanceInsight $row)
            {
                return e(Str::limit(strip_tags((string) $row->message), 120));
            })
            ->rawColumns(['performance_ratio'])
            ->setRowId('id');
    }

    public function query(UserDailyPerformanceInsight $model): QueryBuilder
    {
        $teamId = (int) (auth()->user()->currentTeam?->id ?? 0);

        $query = $model->newQuery()
            ->where('team_id', $teamId)
            ->with([
                'user:id,name',
            ]);

        $date = request('insight_date');
        if ($date)
        {
            $query->whereDate('insight_date', $date);
        } else
        {
            $query->where('insight_date', '>=', now()->subDays(60)->toDateString());
        }

        return $query->orderByDesc('insight_date')->orderByDesc('performance_ratio');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('user-daily-performance-insights-table')
            ->columns($this->getColumns())
            ->minifiedAjax(url()->full())
            ->dom('frtip')
            ->orderBy(1, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
            ]);
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')->title('ID')->hidden(),
            Column::make('insight_date')->title(__('app.performance_insight_column_date')),
            Column::computed('user_name')->title(__('app.performance_insight_column_user'))->orderable(false)->searchable(false),
            Column::make('performance_ratio')->title(__('app.performance_insight_column_ratio')),
            Column::make('headline')->title(__('app.performance_insight_column_headline')),
            Column::make('focus')->title(__('app.performance_insight_column_focus'))->orderable(false),
            Column::make('message')->title(__('app.performance_insight_column_message'))->orderable(false),
        ];
    }
}
