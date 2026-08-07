<?php

namespace App\DataTables;

use App\Models\Project;
use App\Support\DataTableFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProjectDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $table = (new EloquentDataTable($query));

        // Add action column (blade view will handle policy-based permissions)
        $table = $table->addColumn('action', 'project.action');

        return $table
            ->setRowId('id')
            ->editColumn('name', function ($data)
            {
                $label = $data->real_name ?: $data->name;
                $link = DataTableFormatter::showLink($data, 'project.show', $label, 'view', [$data->id]);

                return '<div class="text-truncate" style="max-width: 180px;">'.$link.'</div>';
            })
            ->editColumn('enterprise_id', function ($data)
            {
                $name = $data->client?->name;

                if (! $name)
                {
                    return '<span class="text-muted">Sin cliente</span>';
                }

                return '<span class="text-truncate d-inline-block" style="max-width: 140px;">'.e($name).'</span>';
            })
            ->filterColumn('enterprise_id', function ($query, $keyword)
            {
                $query->whereHas('client', function ($q) use ($keyword)
                {
                    $q->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->editColumn('category_id', function ($data)
            {
                return $data->category ? e($data->category->name) : '<span class="text-muted">Sin categoría</span>';
            })
            ->filterColumn('category_id', function ($query, $keyword)
            {
                $query->whereHas('category', function ($q) use ($keyword)
                {
                    $q->whereRaw('name LIKE ?', ["%{$keyword}%"]);
                });
            })
            ->editColumn('date_start', function ($data)
            {
                return $data->date_start ? Carbon::parse($data->date_start)->format('d-m-Y') : '-';
            })
            ->editColumn('date_end', function ($data)
            {
                return $data->date_end ? Carbon::parse($data->date_end)->format('d-m-Y') : '-';
            })
            ->addColumn('responsible_name', function ($contact)
            {
                return $contact->responsible?->name ?? '<span class="text-muted">Sin asignar</span>';
            })
            ->filterColumn('responsible_name', function ($query, $keyword)
            {
                $query->whereHas('responsible', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('status_id', function ($row)
            {
                return $row->status_label;
            })
            ->filterColumn('status_id', function ($query, $keyword)
            {
                $keyword = trim((string) $keyword);

                if ($keyword === '')
                {
                    return;
                }

                $statusIds = array_values(array_filter(array_map('intval', preg_split('/[|,]/', $keyword) ?: [])));

                if ($statusIds === [])
                {
                    return;
                }

                $query->whereIn('status_id', $statusIds);
            })
            ->rawColumns(['action', 'name', 'status_id', 'enterprise_id', 'category_id', 'responsible_name']);
    }

    public function query(Project $model): QueryBuilder
    {
        $query = $model->newQuery()->with([
            'client',
            'responsible:id,name',
            'category',
            'status',
        ]);

        $user = Auth::user();
        if ($user && $user->hasRole('collaborator'))
        {
            $query->where('responsible_id', $user->id);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('project-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(0, 'desc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'autoWidth' => false,
                'initComplete' => "function() {
					var api = this.api();

					\$('.filter-status').off('click.projectFilter').on('click.projectFilter', function(e) {
						e.preventDefault();
						var status = \$(this).data('status');
						api.column('status_id:name').search(status ? status : '').draw();
					});
				}",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Name'))
                ->addClass('all'),
            Column::make('enterprise_id')
                ->title(__('Client'))
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false),
            Column::make('category_id')
                ->title(__('Category'))
                ->className('text-center')
                ->addClass('none')
                ->searchable(true)
                ->orderable(false)
                ->visible(false),
            Column::make('responsible_name')
                ->title(__('Responsible'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false),
            Column::make('date_end')
                ->title(__('Delivery'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false)
                ->orderable(false),
            Column::make('status_id')
                ->title(__('Status'))
                ->className('text-center')
                ->addClass('all')
                ->name('status_id'),
            Column::computed('action')
                ->title(__('Actions'))
                ->className('text-center')
                ->addClass('all')
                ->orderable(false)
                ->searchable(false)
                ->exportable(false)
                ->printable(false)
                ->width(90),
        ];
    }

    protected function filename(): string
    {
        return 'Project_'.date('YmdHis');
    }
}
