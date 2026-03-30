<?php

namespace App\DataTables;

use App\Models\Opportunity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class OpportunityDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function (Opportunity $row)
            {
                return view('opportunity.action', [
                    'id' => $row->id,
                    'contact' => $row->contact,
                ])->render();
            })
            ->setRowId('id')
            ->editColumn('contact_id', function (Opportunity $row)
            {
                $c = $row->contact;

                return '<div class="d-flex flex-column"><span class="fw-medium">'.e($c->name).' '.e((string) $c->surname).'</span>'
                    .'<small class="text-muted">'.e((string) $c->email).'</small></div>';
            })
            ->filterColumn('contact_id', function ($query, $keyword)
            {
                $query->whereHas('contact', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('surname', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('opportunity_stage_id', function (Opportunity $row)
            {
                return e($row->stage ? $row->stage->localizedName() : '—');
            })
            ->editColumn('estimated_amount', function (Opportunity $row)
            {
                if ($row->estimated_amount === null)
                {
                    return '—';
                }

                $cur = $row->currency?->code ?? '';

                return e(number_format((float) $row->estimated_amount, 2)).($cur !== '' ? ' '.e($cur) : '');
            })
            ->editColumn('opened_at', function (Opportunity $row)
            {
                return $row->opened_at ? Carbon::parse($row->opened_at)->format('d-m-Y') : '—';
            })
            ->addColumn('responsible_name', function (Opportunity $row)
            {
                return e($row->responsible?->name ?? __('Unassigned'));
            })
            ->filterColumn('responsible_name', function ($query, $keyword)
            {
                $query->whereHas('responsible', function ($q) use ($keyword)
                {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['action', 'contact_id']);
    }

    public function query(Opportunity $model): QueryBuilder
    {
        $query = $model->newQuery()->with([
            'contact:id,name,surname,email',
            'responsible:id,name',
            'stage:id,name',
            'currency:id,code',
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
            ->setTableId('opportunity-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'desc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json']);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Name'))
                ->addClass('all'),
            Column::make('contact_id')
                ->title(__('Contact'))
                ->searchable(true)
                ->orderable(false),
            Column::make('opportunity_stage_id')
                ->title(__('Stage'))
                ->searchable(false)
                ->orderable(false),
            Column::make('estimated_amount')
                ->title(__('Estimated amount'))
                ->searchable(false)
                ->orderable(false),
            Column::make('opened_at')
                ->title(__('Opened'))
                ->searchable(false),
            Column::make('responsible_name')
                ->title(__('Responsible'))
                ->searchable(true)
                ->orderable(false),
            Column::computed('action')
                ->title(__('Actions'))
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center'),
        ];
    }
}
