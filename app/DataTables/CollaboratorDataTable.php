<?php

namespace App\DataTables;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CollaboratorDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', function ($contact) {
                return view('collaborator.action', compact('contact'));
            })
            ->addColumn('rating', function ($contact) {
                // Depending on the contact's status, return different rating badges
                $status = $contact->status ?? 'top'; // Default to 'top' if no status
                
                switch($status) {
                    case 'top':
                        return '<div class="d-flex align-items-center"><i class="ti ti-star-filled text-warning ti-sm me-2"></i> Top</div>';
                    case 'blacklist':
                        return '<div class="d-flex align-items-center"><i class="ti ti-x text-danger ti-sm me-2"></i> Lista negra</div>';
                    case 'validated':
                        return '<div class="d-flex align-items-center"><i class="ti ti-check text-success ti-sm me-2"></i> Validada</div>';
                    case 'pending':
                        return '<div class="d-flex align-items-center"><i class="ti ti-clock text-warning ti-sm me-2"></i> En espera</div>';
                    case 'watch':
                        return '<div class="d-flex align-items-center"><i class="ti ti-eye text-danger ti-sm me-2"></i> Ojo</div>';
                    default:
                        return '<div class="d-flex align-items-center"><i class="ti ti-star-filled text-warning ti-sm me-2"></i> Top</div>';
                }
            })
            ->addColumn('language_combinations', function ($contact) {
                // This should be pulled from the contact's language combinations
                $combinations = $contact->language_combinations ?? [];
                
                if (empty($combinations)) {
                    return 'ES > EN';
                }
                
                return implode('<br>', $combinations);
            })
            ->addColumn('services', function ($contact) {
                // This should be pulled from the contact's services
                $services = $contact->services ?? [];
                
                if (empty($services)) {
                    return 'Documentos';
                }
                
                return implode('<br>', $services);
            })
            ->addColumn('projects', function ($contact) {
                // Use a fixed number or random until we have a proper relationship
                return '<span class="badge bg-label-primary rounded-pill">' . rand(0, 10) . '</span>';
            })
            ->setRowId('id')
            ->filterColumn('language_combinations', function($query, $keyword) {
                if ($keyword !== '') {
                    // Implement language combination filtering
                }
            })
            ->filterColumn('services', function($query, $keyword) {
                if ($keyword !== '') {
                    // Implement services filtering
                }
            })
            ->rawColumns(['name', 'action', 'rating', 'language_combinations', 'services', 'projects']);
    }

    public function query(Contact $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('collaborator-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('rtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->parameters([
                'initComplete' => "function() {
                    var api = this.api();
                    api.columns('.select-filter').every(function() {
                        var column = this;
                        $('#CategoryFilter').on('change', function() {
                            let selectedValue = $(this).val();
                            api.column(3).search(selectedValue ? selectedValue : '', true, false).draw();
                        });
                    });
                }",
                'drawCallback' => "function() {
                    $('#CategoryFilter').off('change').on('change', function() {
                        let selectedValue = $(this).val();
                        $('#collaborator-table').DataTable().column(3).search(selectedValue ? selectedValue : '', true, false).draw();
                    });
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')
                ->title(__('Colaborador'))
                ->addClass('all'),
            Column::make('rating')
                ->title(__('Valoración'))
                ->className('text-center')
                ->addClass('min-phone')
                ->searchable(false),
            Column::make('language_combinations')
                ->title(__('Combinación'))
                ->className('text-center')
                ->addClass('min-tablet')
                ->searchable(true),
            Column::make('services')
                ->title(__('Servicios'))
                ->className('text-center')
                ->addClass('min-tablet')
                ->searchable(true),
            Column::make('projects')
                ->title(__('Proyectos'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->searchable(false),
            Column::computed('action')
                ->title(__('Acciones'))
                ->className('text-center')
                ->addClass('min-desktop')
                ->exportable(false)
                ->printable(false)
                ->width(30),
        ];
    }

    protected function filename(): string
    {
        return 'Collaborator_' . date('YmdHis');
    }
} 