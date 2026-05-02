<?php

namespace App\DataTables;

use App\Enums\CampaignType;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CampaignDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<Campaign>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('campaign_display', fn (Campaign $row): string => $this->campaignCell($row))
            ->addColumn('type_display', fn (Campaign $row): string => $this->typeCell($row))
            ->addColumn('performance_display', fn (Campaign $row): string => $this->performanceCell($row))
            ->editColumn('status', fn (Campaign $row): string => $this->statusCell($row))
            ->addColumn('action', fn (Campaign $row): string => view('campaigns.datatable-actions', ['campaign' => $row])->render())
            ->rawColumns(['campaign_display', 'type_display', 'performance_display', 'status', 'action'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<Campaign>
     */
    public function query(Campaign $model): QueryBuilder
    {
        $user = Auth::user();
        abort_unless($user && $user->currentTeam, 403);

        $request = request();

        $query = $model->newQuery()
            ->where('team_id', $user->current_team_id)
            ->with([
                'messages' => function ($q): void
                {
                    $q->select('messages.id', 'messages.team_id', 'messages.status_id', 'messages.started_at');
                },
            ])
            ->orderByDesc('campaigns.created_at');

        $searchKeywordRaw = $request->input('search.value');
        if (is_string($searchKeywordRaw) && trim($searchKeywordRaw) !== '')
        {
            $keyword = trim($searchKeywordRaw);
            $query->where(function (QueryBuilder $sub) use ($keyword): void
            {
                $sub->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('summary', 'like', '%'.$keyword.'%');
            });
        }

        if ($request->filled('campaign_type_filter'))
        {
            $query->where('type', $request->string('campaign_type_filter')->toString());
        }

        if ($request->filled('campaign_status_filter'))
        {
            $query->where('status', $request->string('campaign_status_filter')->toString());
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $locale = session()->get('locale', app()->getLocale());

        return $this->builder()
            ->setTableId('campaigns-table')
            ->columns($this->getColumns())
            ->ajax([
                'url' => route('campaigns.index'),
                'type' => 'GET',
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'data' => 'function (d) {
                    d.campaign_type_filter = $("#campaign-type-filter").val() || "";
                    d.campaign_status_filter = $("#campaign-status-filter").val() || "";
                }',
            ])
            ->dom('t<"row mt-3 align-items-center"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6 d-flex justify-content-md-end"p>>')
            ->pageLength(10)
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.$locale.'.json'])
            ->parameters($this->tableDomParameters());
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::computed('campaign_display')
                ->title(__('Campaña'))
                ->addClass('align-top')
                ->orderable(false)
                ->exportable(false)
                ->printable(false),
            Column::computed('type_display')
                ->title(__('Type'))
                ->className('text-center align-middle')
                ->width(120)
                ->orderable(false)
                ->searchable(false)
                ->exportable(false)
                ->printable(false),
            Column::computed('performance_display')
                ->title(__('Rendimiento'))
                ->addClass('align-top')
                ->exportable(false)
                ->printable(false),
            Column::make('status')
                ->title(__('Estado'))
                ->className('text-center align-middle')
                ->width(140)
                ->orderable(false)
                ->searchable(false)
                ->exportable(false),
            Column::computed('action')
                ->title(__('Acciones'))
                ->className('text-center align-middle')
                ->orderable(false)
                ->searchable(false)
                ->width(140)
                ->exportable(false)
                ->printable(false),
        ];
    }

    protected function filename(): string
    {
        return 'Campaigns_'.date('YmdHis');
    }

    private function campaignCell(Campaign $row): string
    {
        $name = e($row->name);
        $summary = $row->summary ? e($row->summary) : '&mdash;';

        return <<<HTML
<div class="d-flex align-items-start gap-3">
    <div>
        <div class="fw-semibold">{$name}</div>
        <small class="text-muted d-block mt-75">{$summary}</small>
    </div>
</div>
HTML;
    }

    private function typeCell(Campaign $row): string
    {
        $case = CampaignType::tryFrom((string) $row->type);
        $label = e($case ? $case->singularLabel() : $row->type);
        $badgeClass = match ($case)
        {
            CampaignType::Broadcasts => 'bg-label-primary',
            CampaignType::Sequences => 'bg-label-success',
            CampaignType::Events => 'bg-label-info',
            CampaignType::ABTests => 'bg-label-warning',
            null => 'bg-label-secondary',
        };

        return '<span class="badge '.$badgeClass.'">'.$label.'</span>';
    }

    private function performanceCell(Campaign $row): string
    {
        $live = $row->deliveryStatistics();

        $sends = $row->sends_count ?? $live['total'];
        $openRate = $row->opened_rate !== null ? (float) $row->opened_rate : $live['open_rate'];
        $clickRate = $row->clicked_rate !== null ? (float) $row->clicked_rate : $live['click_rate'];

        return '<div class="d-flex flex-wrap gap-3">'
            .$this->metricBlock(__('Envíos'), (string) $sends)
            .$this->metricBlock(__('Abiertos'), $this->formatPercent($openRate))
            .$this->metricBlock(__('Clics'), $this->formatPercent($clickRate))
            .$this->metricBlock(__('Desuscritos'), $this->formatPercent($row->unsubscribed_rate))
            .'</div>';
    }

    private function metricBlock(string $label, string $value): string
    {
        $labelEscaped = e($label);

        return <<<HTML
<div>
    <small class="text-muted d-block">{$labelEscaped}</small>
    <span class="fw-medium">{$value}</span>
</div>
HTML;
    }

    private function formatPercent(mixed $value): string
    {
        if ($value === null)
        {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'%';
    }

    private function statusCell(Campaign $row): string
    {
        $badge = e($row->statusBadgeClasses());
        $label = e($row->statusLabel());

        return '<span class="badge '.$badge.'">'.$label.'</span>';
    }

    /**
     * @return array<string, mixed>
     */
    private function tableDomParameters(): array
    {
        return [
            'select' => false,
            'stateSave' => true,
            'initComplete' => 'function () { var api = this.api(); var debTimer; $("#campaign-search-filter").off(\'keyup.campaignsDt\').on(\'keyup.campaignsDt\', function () { clearTimeout(debTimer); var el = this; debTimer = setTimeout(function () { api.search(el.value || \'\').draw(); }, 275); }); $("#campaign-type-filter, #campaign-status-filter").off(\'change.campaignsDt select2:select select2:clear\').on(\'change.campaignsDt select2:select select2:clear\', function () { api.ajax.reload(); }); }',
            'drawCallback' => 'function () { $("#campaigns-table tbody tr").css({"user-select": "none","-webkit-user-select": "none","-moz-user-select": "none","-ms-user-select": "none"}); }',
        ];
    }
}
