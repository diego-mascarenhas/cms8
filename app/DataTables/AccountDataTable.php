<?php

namespace App\DataTables;

use App\Helpers\TokenHelper;
use App\Models\Account;
use App\Models\Team;
use App\Services\TeamBillingUsageSummaryService;
use App\Support\TeamUsageInvoiceFrequency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccountDataTable extends DataTable
{
    /** @var array<int, Team> */
    private array $teams = [];

    /** @var array<int, array<string, mixed>> */
    private array $usages = [];

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', function ($account)
            {
                return view('account.name-cell', ['account' => $account])->render();
            })
            ->filterColumn('name', function ($query, $keyword)
            {
                $query->whereResponsibleMatches($keyword);
            })
            ->addColumn('owner_name', function ($account)
            {
                return view('account.owner-cell', ['owner' => $account->owner])->render();
            })
            ->filterColumn('owner_name', function ($query, $keyword)
            {
                $query->whereResponsibleMatches($keyword);
            })
            ->addColumn('renews_at', function ($account)
            {
                return $this->formatRenewalDate($account->renews_at ?? null);
            })
            ->orderColumn('renews_at', function ($query, string $order): void
            {
                $this->orderByNextRenewal($query, strtolower($order) === 'desc' ? 'desc' : 'asc');
            })
            ->addColumn('usage_billed', function ($account)
            {
                return $this->formatUsageBilled($account);
            })
            ->orderColumn('usage_billed', function ($query, string $order): void
            {
                $this->orderByUsageBilled($query, strtolower($order) === 'desc' ? 'desc' : 'asc');
            })
            ->addColumn('action', function ($account)
            {
                $autologinButtons = '';
                if ($account->owner)
                {
                    $token = TokenHelper::generateSignedToken($account->owner, 'account_owner_autologin', 720); // 30 days
                    $loginUrl = route('login.token', ['token' => $token]);
                    $fullUrl = url($loginUrl);

                    $autologinButtons = '<a href="javascript:;"
                                           class="text-body"
                                           onclick="copyAutologinLink(\''.addslashes($fullUrl).'\', this)"
                                           title="Copiar link de autologueo">
                                            <i class="ti ti-link ti-sm me-2"></i>
                                        </a>
                                        <a href="javascript:;"
                                           class="text-info"
                                           onclick="sendAutologinInvitation('.$account->id.', this)"
                                           title="Enviar invitación por email">
                                            <i class="ti ti-send ti-sm me-2"></i>
                                        </a>
                                        <a href="javascript:;"
                                           class="text-body"
                                           onclick="changeAccountPassword('.$account->id.')"
                                           title="Cambiar contraseña">
                                            <i class="ti ti-key ti-sm me-2"></i>
                                        </a>
                                        <a href="javascript:;"
                                           class="text-danger"
                                           onclick="revokeAutologinToken('.$account->id.', this)"
                                           title="Revocar tokens de autologueo">
                                            <i class="ti ti-x ti-sm me-2"></i>
                                        </a>';
                }

                return '<div class="d-flex justify-content-center align-items-center">
					<a href="'.route('account.subscriptions', $account->id).'" class="text-body" title="Ver suscripciones">
						<i class="ti ti-eye ti-sm me-2"></i>
					</a>
					'.$autologinButtons.'
					<a href="'.route('account.rates.edit', $account->id).'" class="text-body" title="Tarifas">
						<i class="ti ti-currency-euro ti-sm me-2"></i>
					</a>
					<a href="'.route('account.edit', $account->id).'" class="text-body" title="Editar">
						<i class="ti ti-edit ti-sm"></i>
					</a>
				</div>';
            })
            ->setRowId('id')
            ->rawColumns(['name', 'owner_name', 'action']);
    }

    public function query(Account $model): QueryBuilder
    {
        return $model->newQuery()
            ->select('teams.*')
            ->selectRaw($this->renewalSelectSql().' as renews_at')
            ->with([
                'owner:id,name,email,phone',
                'billingEnterprise.contacts' => function ($query)
                {
                    $query->withoutGlobalScopes();
                },
            ]);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('account-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(3, direction: 'asc')
            ->responsive(true)
            ->processing(true)
            ->serverSide(true)
            ->pageLength(25)
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
            ->parameters([
                'autoWidth' => false,
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden()->searchable(false),
            Column::make('name')
                ->title(__('Responsable'))
                ->addClass('all')
                ->width('200px')
                ->orderable(true)
                ->searchable(true),
            Column::computed('owner_name')
                ->title(__('Contacto'))
                ->className('text-start')
                ->addClass('all')
                ->width('180px')
                ->orderable(true)
                ->searchable(true),
            Column::computed('renews_at')
                ->title(__('Renovación'))
                ->className('text-center text-nowrap')
                ->addClass('all')
                ->width('100px')
                ->orderable(true)
                ->searchable(false),
            Column::computed('usage_billed')
                ->title(__('A facturar'))
                ->className('text-end text-nowrap')
                ->addClass('all')
                ->width('110px')
                ->orderable(true)
                ->searchable(false),
            Column::computed('action')
                ->title('Acciones')
                ->className('text-center')
                ->addClass('all')
                ->width('140px')
                ->orderable(false)
                ->searchable(false),
        ];
    }

    protected function filename(): string
    {
        return 'Account_'.date('YmdHis');
    }

    private function renewalSelectSql(): string
    {
        $cycleKey = TeamUsageInvoiceFrequency::PERIOD_STARTS_AT_KEY;

        return 'coalesce(
            (select '.$this->asTextSql('ss.current_period_end').'
             from subscriptions s
             inner join service_syncs ss on ss.stripe_id = s.stripe_id
             where s.team_id = teams.id
               and s.type = \'assistant\'
               and s.stripe_status in (\'active\', \'trialing\')
               and ss.current_period_end is not null
             order by s.created_at asc
             limit 1),
            (select '.$this->asTextSql('min(ss2.current_period_end)').'
             from service_syncs ss2
             where ss2.customer_id = teams.stripe_id
               and teams.stripe_id is not null
               and teams.stripe_id != \'\'
               and ss2.status in (\'active\', \'trialing\')
               and ss2.current_period_end is not null),
            (select ts.value
             from team_settings ts
             where ts.team_id = teams.id
               and ts.key = \''.$cycleKey.'\'
             limit 1)
        )';
    }

    private function asTextSql(string $expression): string
    {
        return match (DB::connection()->getDriverName())
        {
            'pgsql' => '('.$expression.')::text',
            'mysql', 'mariadb' => 'cast('.$expression.' as char)',
            default => $expression,
        };
    }

    private function orderByNextRenewal(QueryBuilder $query, string $direction): void
    {
        $rows = Account::query()
            ->select('teams.id')
            ->selectRaw($this->renewalSelectSql().' as renews_at')
            ->get();

        $sorted = $rows->sortBy(
            fn ($row): int => $this->nextRenewalAt($row->renews_at)?->timestamp ?? PHP_INT_MAX,
            SORT_REGULAR,
            $direction === 'desc',
        );

        $this->applyIdOrder($query, $sorted);
    }

    private function orderByUsageBilled(QueryBuilder $query, string $direction): void
    {
        $rows = Account::query()
            ->select('teams.id')
            ->selectRaw($this->renewalSelectSql().' as renews_at')
            ->get();

        $sorted = $rows->sortBy(
            fn ($row): int => $this->usageBilledCents($row),
            SORT_REGULAR,
            $direction === 'desc',
        );

        $this->applyIdOrder($query, $sorted);
    }

    /**
     * @param  Collection<int, Account>  $sorted
     */
    private function applyIdOrder(QueryBuilder $query, Collection $sorted): void
    {
        $cases = [];
        foreach (array_values($sorted->all()) as $position => $row)
        {
            $cases[] = 'when '.(int) $row->id.' then '.$position;
        }

        if ($cases === [])
        {
            return;
        }

        $query->orderByRaw('case teams.id '.implode(' ', $cases).' else '.count($cases).' end');
    }

    private function formatRenewalDate(mixed $raw): string
    {
        return $this->nextRenewalAt($raw)?->format('d/m/Y') ?? '—';
    }

    private function formatUsageBilled(Account $account): string
    {
        return $this->usageFor($account)['formatted']['billed'];
    }

    private function usageBilledCents(Account $account): int
    {
        return (int) $this->usageFor($account)['billed_cents'];
    }

    /**
     * @return array<string, mixed>
     */
    private function usageFor(Account $account): array
    {
        $id = (int) $account->id;

        return $this->usages[$id] ??= app(TeamBillingUsageSummaryService::class)
            ->forOpenWindow($this->teamFor($account), $this->nextRenewalAt($account->renews_at ?? null));
    }

    private function teamFor(Account $account): Team
    {
        $id = (int) $account->id;

        return $this->teams[$id] ??= Team::query()->findOrFail($id);
    }

    private function nextRenewalAt(mixed $raw): ?Carbon
    {
        if ($raw === null || trim((string) $raw) === '')
        {
            return null;
        }

        $at = Carbon::parse((string) $raw);
        if ($at->lte(now()))
        {
            return TeamUsageInvoiceFrequency::monthlyWindow($at, $at->day, now())[1];
        }

        return $at;
    }
}
