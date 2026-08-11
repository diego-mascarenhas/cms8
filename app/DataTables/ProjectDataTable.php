<?php

namespace App\DataTables;

use App\Models\Category;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectStatus;
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

        $statusFilter = trim((string) request()->input('status_filter', ''));
        if ($statusFilter !== '')
        {
            $statusIds = array_values(array_filter(array_map('intval', preg_split('/[|,]/', $statusFilter) ?: [])));
            if ($statusIds !== [])
            {
                $query->whereIn('status_id', $statusIds);
            }
        }

        $categoryFilter = trim((string) request()->input('category_filter', ''));
        if ($categoryFilter === 'none')
        {
            $query->whereNull('category_id');
        } elseif ($categoryFilter !== '' && ctype_digit($categoryFilter))
        {
            $query->where('category_id', (int) $categoryFilter);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $statusLabel = e(__('Status'));
        $categoryLabel = e(__('Category'));
        $allLabel = e(__('All'));
        $uncategorizedLabel = e(__('Uncategorized'));

        $statusOptionsHtml = '<option value="">'.$allLabel.'</option>';
        $statusOptionsHtml .= '<option value="1">'.e(__('project_status.BUDGET')).'</option>';
        $statusOptionsHtml .= '<option value="2">'.e(__('project_status.BUDGETED')).'</option>';
        $statusOptionsHtml .= '<option value="3,7,8,9">'.e(__('project_status.IN_PROGRESS')).'</option>';
        $statusOptionsHtml .= '<option value="10,11">'.e(__('project_status.TO_INVOICE')).'</option>';
        $statusOptionsHtml .= '<option disabled>──────────</option>';

        foreach (ProjectStatus::getOptions() as $status)
        {
            $statusOptionsHtml .= '<option value="'.e((string) $status['id']).'">'.e((string) $status['name']).'</option>';
        }

        $categoryOptionsHtml = '<option value="">'.$allLabel.'</option>';
        $categoryOptionsHtml .= '<option value="none">'.$uncategorizedLabel.'</option>';

        foreach ($this->projectCategoryFilterOptions() as $category)
        {
            $categoryOptionsHtml .= '<option value="'.e((string) $category['id']).'">'.e((string) $category['name']).'</option>';
        }

        $initComplete = "function () {
    var api = this.api();
    var f = jQuery('#project-table_filter');
    if (! f.length) { return; }
    f.addClass('d-flex flex-wrap align-items-center justify-content-between column-gap-3 row-gap-2');
    if (! jQuery('#project-filter-status').length) {
        f.prepend(
            '<div id=\"project-table-filters\" class=\"d-flex flex-wrap align-items-center gap-2 flex-shrink-1\">' +
            '<div class=\"d-inline-flex align-items-center flex-shrink-0\">' +
            '<label for=\"project-filter-status\" class=\"form-label mb-0 me-1 text-nowrap small\">{$statusLabel}</label>' +
            '<select id=\"project-filter-status\" class=\"form-select form-select-sm\" style=\"width:10rem;\">{$statusOptionsHtml}</select>' +
            '</div>' +
            '<div class=\"d-inline-flex align-items-center flex-shrink-0\">' +
            '<label for=\"project-filter-category\" class=\"form-label mb-0 me-1 text-nowrap small\">{$categoryLabel}</label>' +
            '<select id=\"project-filter-category\" class=\"form-select form-select-sm\" style=\"width:12rem;\">{$categoryOptionsHtml}</select>' +
            '</div></div>'
        );
    }
    f.find('input[type=\"search\"]').closest('label').addClass('ms-auto mb-0 flex-shrink-0 text-nowrap');
    jQuery('#project-filter-status, #project-filter-category').off('change.projectFilters').on('change.projectFilters', function () {
        api.ajax.reload();
    });
    jQuery('.filter-status').off('click.projectFilter').on('click.projectFilter', function (e) {
        e.preventDefault();
        var status = jQuery(this).data('status');
        jQuery('#project-filter-status').val(status ? String(status) : '').trigger('change');
    });
}";

        return $this
            ->builder()
            ->setTableId('project-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                '',
                "data.status_filter = ($('#project-filter-status').val() || ''); data.category_filter = ($('#project-filter-category').val() || '');",
            )
            ->dom('frtip')
            ->orderBy(0, 'desc')
            ->responsive(true)
            ->processing(false)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/es.json'])
            ->parameters([
                'autoWidth' => false,
                'initComplete' => $initComplete,
                'drawCallback' => "function () {
                    var f = jQuery('#project-table_filter');
                    f.addClass('d-flex flex-wrap align-items-center justify-content-between column-gap-3 row-gap-2');
                    f.find('input[type=\"search\"]').closest('label').addClass('ms-auto mb-0 flex-shrink-0 text-nowrap');
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
                ->addClass('min-tablet')
                ->searchable(true)
                ->orderable(false),
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

    /**
     * @return list<array{id: int, name: string}>
     */
    private function projectCategoryFilterOptions(): array
    {
        $moduleId = Module::query()->where('key', 'projects')->value('id');
        if (! $moduleId)
        {
            return [];
        }

        $teamId = Auth::user()?->currentTeam?->id;

        return Category::query()
            ->where('module_id', (int) $moduleId)
            ->where('status', '>', 0)
            ->whereNotNull('parent_id')
            ->where(function ($query) use ($teamId)
            {
                $query->whereNull('team_id');
                if ($teamId)
                {
                    $query->orWhere('team_id', $teamId);
                }
            })
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
            ])
            ->all();
    }
}
