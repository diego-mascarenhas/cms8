<?php

namespace App\DataTables;

use App\Models\Enterprise;
use App\Models\Service;
use App\Models\StripeSubscription;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class StripeSubscriptionDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->setRowId('id')
            ->rawColumns(['status', 'action'])
            ->filterColumn('customer_name', function (QueryBuilder $builder, string $keyword)
            {
                $builder->where('stripe_subscriptions.customer_name', 'like', '%'.$keyword.'%');
            })
            ->filterColumn('enterprise_contact_search', function (QueryBuilder $builder, string $keyword)
            {
                $like = '%'.$keyword.'%';

                $builder->where(function (QueryBuilder $q) use ($like)
                {
                    $q->whereHas('enterprise', function (QueryBuilder $eq) use ($like)
                    {
                        $eq->whereColumn('enterprises.team_id', 'stripe_subscriptions.team_id')
                            ->whereNull('enterprises.deleted_at')
                            ->where(function (QueryBuilder $inner) use ($like)
                            {
                                $inner->where('enterprises.name', 'like', $like)
                                    ->orWhereHas('contacts', function (QueryBuilder $cq) use ($like)
                                    {
                                        $cq->whereNull('contacts.deleted_at')
                                            ->where(function (QueryBuilder $c2) use ($like)
                                            {
                                                $c2->where('contacts.name', 'like', $like)
                                                    ->orWhere('contacts.surname', 'like', $like);
                                            });
                                    });
                            });
                    });
                });
            })
            ->addColumn('enterprise_contact_search', fn () => '')
            ->addColumn('action', function (StripeSubscription $sub)
            {
                $user = auth()->user();
                if (! $user)
                {
                    return '<span class="text-muted">—</span>';
                }

                $parts = [];

                $service = Service::withoutGlobalScopes()
                    ->where('subscription_id', $sub->id)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->get()
                    ->first(fn (Service $s) => $user->can('view', $s));

                if ($service)
                {
                    $parts[] = '<a href="'.e(route('service.show', $service->id)).'" class="text-body" title="'.e(__('stripe_subscription.open_service')).'">'
                        .'<i class="ti ti-eye ti-sm"></i>'
                        .'</a>';
                }

                $enterpriseId = $sub->getAttribute('enterprise_match_id');
                if ($enterpriseId)
                {
                    $enterprise = Enterprise::query()->find($enterpriseId);
                    if ($enterprise && $user->can('view', $enterprise))
                    {
                        $parts[] = '<a href="'.e(route('client.show', $enterprise->id)).'" class="text-body" title="'.e(__('stripe_subscription.open_client')).'">'
                            .'<i class="ti ti-building ti-sm"></i>'
                            .'</a>';

                        $contact = $enterprise->contacts()->first();
                        if ($contact && $user->can('view', $contact))
                        {
                            $parts[] = '<a href="'.e(route('contact.show', $contact->id)).'" class="text-body" title="'.e(__('stripe_subscription.open_contact')).'">'
                                .'<i class="ti ti-user ti-sm"></i>'
                                .'</a>';
                        }
                    }
                }

                if ($parts === [])
                {
                    return '<span class="text-muted">—</span>';
                }

                return '<div class="d-flex justify-content-center align-items-center gap-1">'.implode('', $parts).'</div>';
            })
            ->editColumn('customer_name', function (StripeSubscription $sub)
            {
                return $sub->customer_name ?: '—';
            })
            ->editColumn('plan_name', function (StripeSubscription $sub)
            {
                return $sub->plan_name ?: $sub->stripe_id;
            })
            ->editColumn('status', function (StripeSubscription $sub)
            {
                $raw = $sub->status;
                if (! $raw)
                {
                    return '<span class="badge bg-secondary">—</span>';
                }

                $badge = match ($raw)
                {
                    'active' => 'success',
                    'trialing' => 'info',
                    'past_due', 'unpaid' => 'warning',
                    'incomplete' => 'dark',
                    'canceled', 'incomplete_expired' => 'secondary',
                    'paused' => 'secondary',
                    default => 'secondary',
                };

                $key = 'stripe_subscription.status.'.$raw;
                $label = __($key);

                if ($label === $key)
                {
                    $label = Str::headline(str_replace('_', ' ', $raw));
                }

                return '<span class="badge bg-'.$badge.'">'.e($label).'</span>';
            })
            ->editColumn('amount_total', function (StripeSubscription $sub)
            {
                if ($sub->amount_total === null)
                {
                    return '—';
                }

                $currency = strtoupper($sub->price_currency ?? 'EUR');

                return number_format((float) $sub->amount_total, 2, ',', '.').' '.$currency;
            })
            ->editColumn('current_period_end', function (StripeSubscription $sub)
            {
                return $sub->current_period_end
                    ? $sub->current_period_end->format('d/m/Y')
                    : '—';
            });
    }

    public function query(StripeSubscription $model): QueryBuilder
    {
        $teamId = auth()->user()->currentTeam?->id;

        $query = $model
            ->newQuery()
            ->select('stripe_subscriptions.*')
            ->leftJoin('enterprises', function ($join)
            {
                $join->on('enterprises.code', '=', 'stripe_subscriptions.customer_id')
                    ->on('enterprises.team_id', '=', 'stripe_subscriptions.team_id')
                    ->whereNull('enterprises.deleted_at');
            })
            ->addSelect('enterprises.id as enterprise_match_id');

        return $query
            ->when($teamId, fn ($q) => $q->where('stripe_subscriptions.team_id', $teamId))
            ->when(! $teamId, fn ($q) => $q->whereRaw('1 = 0'));
    }

    public function html(): HtmlBuilder
    {
        return $this
            ->builder()
            ->setTableId('stripe-subscription-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('frtip')
            ->orderBy(1, 'asc')
            ->responsive(true)
            ->processing(false)
            ->language(['url' => '/js/datatables/'.session()->get('locale', app()->getLocale()).'.json'])
            ->parameters([
                'select' => false,
                'autoWidth' => false,
                'drawCallback' => 'function() {
                    $("#stripe-subscription-table tbody tr").css({
                        "user-select": "none",
                        "-webkit-user-select": "none",
                        "-moz-user-select": "none",
                        "-ms-user-select": "none"
                    });
                }',
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->hidden(),
            Column::make('customer_name')->title(__('stripe_subscription.columns.customer_name'))->addClass('all')->searchable(true)->orderable(true),
            Column::make('plan_name')->title(__('stripe_subscription.columns.plan_name'))->addClass('min-tablet')->searchable(true)->orderable(true),
            Column::make('status')->title(__('stripe_subscription.columns.status'))->addClass('min-phone')->className('text-center')->orderable(true),
            Column::make('amount_total')->title(__('stripe_subscription.columns.amount_total'))->addClass('min-desktop')->className('text-end')->orderable(true),
            Column::make('current_period_end')->title(__('stripe_subscription.columns.current_period_end'))->addClass('min-desktop')->className('text-center')->orderable(true),
            Column::make('enterprise_contact_search')
                ->title('')
                ->visible(false)
                ->searchable(true)
                ->orderable(false)
                ->exportable(false)
                ->printable(false),
            Column::computed('action')
                ->title(__('stripe_subscription.columns.actions'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(72)
                ->addClass('min-desktop')
                ->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'StripeSubscription_'.date('YmdHis');
    }
}
