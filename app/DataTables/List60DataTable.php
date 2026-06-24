<?php

namespace App\DataTables;

use App\Models\List60;
use App\Support\ApplicationDateTime;
use App\Support\DataTableFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class List60DataTable extends DataTable
{
    /** @var Collection<int, \App\Models\User> */
    public Collection $teamUsers;

    public function __construct()
    {
        $this->teamUsers = collect();
    }

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($row)
            {
                return view('list60.action', [
                    'id' => $row->id,
                    'contact' => $row->contact,
                ]);
            })
            ->setRowId('id')
            ->editColumn('contact_id', function ($row)
            {
                // Mostrar la primera empresa asociada al contacto (si existe)
                $companyName = $row->contact->enterprises->first() ? e($row->contact->enterprises->first()->name) : '';

                $nameHtml = DataTableFormatter::showLink(
                    $row->contact,
                    'contact.show',
                    $row->contact->name,
                    'view',
                    [$row->contact->id],
                );

                return DataTableFormatter::nameColumn($nameHtml, $companyName ?: null);
            })
            ->addColumn('list60_status', function ($row)
            {
                return $row->status_label;
            })
            ->addColumn('responsible_name', function ($row)
            {
                if (auth()->user()->hasRole('admin'))
                {
                    return view('list60.responsible-select', [
                        'list60Id' => $row->id,
                        'responsibleId' => $row->responsible_id,
                        'teamUsers' => $this->teamUsers,
                    ])->render();
                }

                return $row->responsible?->name ?? __('Unassigned');
            })
            ->editColumn('date_next', function ($row)
            {
                $parsed = Carbon::parse($row->date_next);
                $iso = $parsed->format('Y-m-d');
                $label = ApplicationDateTime::formatUpcomingContactDate($parsed);
                $title = e(ApplicationDateTime::formatUpcomingContactDateTitle($parsed));

                return '<span data-field="date_next" data-value="'.$iso.'" title="'.$title.'">'.e($label).'</span>';
            })
            ->addColumn('categories', function ($row)
            {
                $badges = $row->contact->categories->map(function ($category)
                {
                    return '<span class="badge bg-label-primary me-1">'.e($category->name).'</span>';
                })->join(' ');

                return $badges !== '' ? $badges : '&nbsp;';
            })
            ->filterColumn('categories', function ($query, $keyword)
            {
                if ($keyword !== '' && is_numeric($keyword))
                {
                    $query->whereHas('contact.categories', function ($q) use ($keyword)
                    {
                        $q->where('id', $keyword);
                    });
                } elseif ($keyword !== '')
                {
                    $query->whereRaw('0 = 1');
                }
            })
            ->filterColumn('contact_id', function ($query, $keyword)
            {
                $query->whereHas('contact', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['name', 'action', 'contact_id', 'list60_status', 'date_next', 'categories', 'responsible_name']);
    }

    public function query(List60 $model): QueryBuilder
    {
        $query = $model->newQuery();

        if (! auth()->user()->hasRole('admin'))
        {
            $query->myResponsibilities();
        }

        return $query->whereHas('contact')
            ->with([
                'contact.enterprises',
                'contact.categories',
                'contact.user.roles',
                'contact.user.teams',
                'status',
                'responsible:id,name',
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('contact-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(3, direction: 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'pageLength' => 60,
                'paging' => false,
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('contact_id')
                ->title(value: __('Name'))
                ->addClass('all')
                ->orderable(false),
            Column::make('list60_status')
                ->title(__('app.list60_contact_count_column'))
                ->className('text-center')
                ->addClass('min-phone')
                ->orderable(false)
                ->searchable(false),
            Column::make('date_next')
                ->title(__('app.list60_next_column'))
                ->className('text-center')
                ->addClass('min-phone'),
            Column::make('categories')
                ->title(__('Categories'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(true)
                ->orderable(false),
            Column::make('responsible_name')
                ->title(__('Responsible'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->orderable(false),
            Column::computed('action')
                ->title(__('Actions'))
                ->width(20)
                ->className('text-center')
                ->addClass('min-desktop')
                ->exportable(false)
                ->printable(false)
                ->width(30),
        ];
    }

    protected function filename(): string
    {
        return 'List60_'.date('YmdHis');
    }
}
