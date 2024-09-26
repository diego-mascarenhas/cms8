<?php

namespace App\DataTables;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use Carbon\Carbon;

class ContactDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'contact.action')
            ->setRowId('id')
            ->editColumn('name', function ($row) {
                return '<div class="d-flex flex-column">
                            <span class="fw-medium text-body text-truncate">' . $row->name . '</span>
                            <small class="text-muted">
                                <a href="mailto:' . $row->email . '">' . $row->email . '</a>
                            </small>
                        </div>';
            })
            ->addColumn('current_sentiment', function ($row) {
                if ($row->currentSentiment) {
                    return $row->currentSentiment->sentiment->emoji;
                }
                return '🤔';
            })
            ->filterColumn('current_sentiment', function($query, $keyword) {
                if ($keyword !== '') {
                    $query->whereHas('currentSentiment', function($q) use ($keyword) {
                        $q->where('sentiment_id', $keyword);
                    });
                }
            })
            ->addColumn('social_networks', function ($row) {
                $socialNetworks = [
                    'whatsapp' => ['icon' => 'fab fa-whatsapp', 'color' => '#25D366'],
                    'facebook' => ['icon' => 'fab fa-facebook', 'color' => '#1877F2'],
                    'instagram' => ['icon' => 'fab fa-instagram', 'color' => '#E4405F'],
                    'twitter' => ['icon' => 'fab fa-twitter', 'color' => '#1DA1F2'],
                    'linkedin' => ['icon' => 'fab fa-linkedin', 'color' => '#0A66C2'],
                    'youtube' => ['icon' => 'fab fa-youtube', 'color' => '#FF0000'],
                    'tiktok' => ['icon' => 'fab fa-tiktok', 'color' => '#000000'],
                    'pinterest' => ['icon' => 'fab fa-pinterest', 'color' => '#BD081C'],
                    'snapchat' => ['icon' => 'fab fa-snapchat', 'color' => '#FFFC00'],
                    'telegram' => ['icon' => 'fab fa-telegram', 'color' => '#0088cc'],
                ];

                $networks = [];
                $data = json_decode(json_encode($row->data), true) ?? [];

                foreach ($socialNetworks as $network => $info) {
                    $value = $data[$network] ?? '';
                    if (!empty($value)) {
                        $networks[] = sprintf(
                            '<a href="%s" target="_blank" style="color: %s;"><i class="%s"></i></a>',
                            $this->getSocialLink($network, $value),
                            $info['color'],
                            $info['icon']
                        );
                    }
                }

                return empty($networks) ? '' : implode(' ', $networks);
            })
            ->addColumn('responsible_name', function ($contact) {
                return $contact->responsible->name ?? 'N/A';
            })
            ->filterColumn('responsible_name', function($query, $keyword) {
                $query->whereHas('responsible', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('status_id', function ($row) {
                return $row->status_label;
            })
            ->rawColumns(['name', 'action', 'current_sentiment', 'social_networks', 'status_id']);
    }

    public function query(Contact $model): QueryBuilder
    {
        return $model->newQuery()->with('currentSentiment.sentiment', 'status', 'responsible');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('contact-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->language(['url' => '/js/datatables/' . session()->get('locale', app()->getLocale()) . '.json'])
            ->parameters([
                'initComplete' => "function() {
                    var api = this.api();
                    api.columns('.select-filter').every(function() {
                        var column = this;
                        $('#EmotionalState').on('change', function() {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            column.search(val ? val : '', true, false).draw();
                        });

                        $('.filter-status').on('click', function(e) {
                            e.preventDefault();
                            var status = $(this).data('status');
                            api.column('status_id:name').search(status).draw();
                        });
                    });
                }",
                'drawCallback' => "function() {
                    $('#EmotionalState').off('change').on('change', function() {
                        $('#contact-table').DataTable().columns('.select-filter').search($(this).val()).draw();
                    });
                }",
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('name')->title('Contacto'),
            Column::make('current_sentiment')
                ->title('Último estado')
                ->className('text-center')
                ->addClass('select-filter')
                ->searchable(true)
                ->orderable(false),
            Column::make('social_networks')
                ->title('Redes')
                ->className('text-center')
                ->searchable(false)
                ->orderable(false)
                ->width(200),
            Column::make('responsible_name')->title('Asesor')->className('text-center'),
            Column::make('status_id')->title('Estado')->className('text-center'),
            Column::computed('action')->title('Acciones')->width(20)->className('text-center')
                ->exportable(false)
                ->printable(false)
                ->width(30)
                ->addClass('text-center'),
        ];
    }

    private function getSocialLink($network, $value)
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $baseUrls = [
            'whatsapp' => 'https://wa.me/',
            'facebook' => 'https://facebook.com/',
            'instagram' => 'https://instagram.com/',
            'twitter' => 'https://twitter.com/',
            'linkedin' => 'https://linkedin.com/in/',
            'youtube' => 'https://youtube.com/',
            'tiktok' => 'https://tiktok.com/@',
            'pinterest' => 'https://pinterest.com/',
            'snapchat' => 'https://snapchat.com/add/',
            'telegram' => 'https://t.me/',
        ];

        return ($baseUrls[$network] ?? '') . $value;
    }

    protected function filename(): string
    {
        return 'Contact_' . date('YmdHis');
    }
}