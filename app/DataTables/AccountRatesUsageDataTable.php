<?php

namespace App\DataTables;

use App\Models\Team;
use App\Services\TeamBillingUsageSummaryService;
use Illuminate\Support\Collection;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccountRatesUsageDataTable extends DataTable
{
    public function dataTable(Collection $query): CollectionDataTable
    {
        return (new CollectionDataTable($query))->setRowId('month');
    }

    public function query(TeamBillingUsageSummaryService $summary): Collection
    {
        $team = Team::query()->findOrFail((int) request()->route('id'));

        return $summary->pastMonths($team)->map(fn (array $row): array => [
            'month' => $row['month'],
            'month_label' => $row['month_label'],
            'tokens' => $row['formatted']['tokens'],
            'whatsapp' => $row['formatted']['whatsapp'],
            'mailer' => $row['formatted']['mailer'],
            'cost' => $row['formatted']['cost'],
            'billed' => $row['formatted']['billed'],
            'markup' => $row['formatted']['markup'],
        ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('account-rates-usage-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('account.rates.edit', request()->route('id')))
            ->dom('frtip')
            ->orderBy(0, 'desc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(12)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'autoWidth' => false,
                'select' => false,
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('month')
                ->title('Periodo')
                ->hidden()
                ->searchable(false),
            Column::make('month_label')
                ->title('Mes')
                ->name('month_label')
                ->data('month_label')
                ->addClass('all'),
            Column::make('tokens')
                ->title('Tokens')
                ->name('tokens')
                ->data('tokens')
                ->className('text-center')
                ->addClass('min-tablet'),
            Column::make('whatsapp')
                ->title('WhatsApp')
                ->name('whatsapp')
                ->data('whatsapp')
                ->className('text-center')
                ->addClass('min-tablet'),
            Column::make('mailer')
                ->title('Mail')
                ->name('mailer')
                ->data('mailer')
                ->className('text-center')
                ->addClass('min-tablet'),
            Column::make('cost')
                ->title('Coste')
                ->name('cost')
                ->data('cost')
                ->className('text-center')
                ->addClass('all'),
            Column::make('billed')
                ->title('Facturado')
                ->name('billed')
                ->data('billed')
                ->className('text-center')
                ->addClass('all'),
            Column::make('markup')
                ->title('Markup')
                ->name('markup')
                ->data('markup')
                ->className('text-center')
                ->addClass('all'),
        ];
    }

    protected function filename(): string
    {
        return 'AccountRatesUsage_'.date('YmdHis');
    }
}
