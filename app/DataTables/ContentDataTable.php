<?php

namespace App\DataTables;

use App\Models\Content;
use App\Support\DataTableFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ContentDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (Content $content)
            {
                return view('contents.action', compact('content'));
            })
            ->setRowId('id')
            ->editColumn('title', function (Content $content)
            {
                $title = $content->resolveAdministrativeTitle();

                if ($title === null)
                {
                    return '<span class="text-muted">'.__('app.No title').'</span>';
                }

                return DataTableFormatter::showLink($content, 'contents.show', $title, 'view', [$content->id]);
            })
            ->editColumn('section_category_id', function (Content $content)
            {
                return $content->sectionCategory
                    ? $content->sectionCategory->name
                    : '<span class="text-muted">'.__('app.No category').'</span>';
            })
            ->editColumn('status', function (Content $content)
            {
                $statusLabels = [
                    1 => __('app.Draft'),
                    2 => __('app.Pending'),
                    3 => __('app.Published'),
                    4 => __('app.Archived'),
                ];

                $statusClasses = [
                    1 => 'bg-label-secondary',
                    2 => 'bg-label-warning',
                    3 => 'bg-label-success',
                    4 => 'bg-label-info',
                ];

                $label = $statusLabels[$content->status] ?? __('app.Unknown');
                $class = $statusClasses[$content->status] ?? 'bg-label-secondary';

                return '<span class="badge rounded-pill '.$class.'">'.$label.'</span>';
            })
            ->editColumn('featured', function (Content $content)
            {
                if ($content->featured)
                {
                    return '<span class="badge rounded-pill bg-label-primary">'.__('app.Yes').'</span>';
                }

                return '<span class="text-muted">'.__('app.No').'</span>';
            })
            ->editColumn('created_at', function (Content $content)
            {
                return $content->created_at
                    ? Carbon::parse($content->created_at)->format('d-m-Y H:i')
                    : '-';
            })
            ->rawColumns(['title', 'section_category_id', 'status', 'featured', 'action']);
    }

    public function query(Content $model): QueryBuilder
    {
        $query = $model->newQuery()->with(['sectionCategory', 'multimedia']);
        $request = $this->request();

        if ($request->filled('section_id'))
        {
            $query->where('section_category_id', $request->get('section_id'));
        }

        if ($request->filled('category_id'))
        {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->filled('status'))
        {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('featured'))
        {
            $query->where('featured', $request->get('featured') == '1');
        }

        $search = $request->input('search.value', $request->input('search'));
        if (is_string($search) && $search !== '')
        {
            $query->where(function ($q) use ($search)
            {
                // Engine-agnostic JSON text search (MySQL/PostgreSQL).
                $q->whereRaw("CONCAT(title, '') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("CONCAT(subtitle, '') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("CONCAT(content, '') LIKE ?", ["%{$search}%"]);
            });
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('content-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                '',
                "data.section_id = $('#filter_section').val() || ''; data.status = $('#filter_status').val() || ''; data.featured = $('#filter_featured').val() || '';",
            )
            ->dom('rtip')
            ->orderBy(5, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->language([
                'url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json',
                'search' => '',
                'searchPlaceholder' => trans('app.Search'),
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('title')->title(trans('app.Title')),
            Column::make('section_category_id')
                ->title(trans('app.Category'))
                ->className('text-center')
                ->orderable(false)
                ->searchable(false),
            Column::make('status')
                ->title(trans('app.Status'))
                ->className('text-center'),
            Column::make('featured')
                ->title(trans('app.Featured'))
                ->className('text-center'),
            Column::make('created_at')
                ->title(trans('app.Created'))
                ->className('text-center'),
            Column::computed('action')
                ->title(trans('app.Actions'))
                ->width(20)
                ->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(30),
        ];
    }

    protected function filename(): string
    {
        return 'Content_'.date('YmdHis');
    }
}
