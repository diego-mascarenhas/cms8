<?php

namespace App\DataTables;

use App\Models\Enterprise;
use App\Models\Service;
use App\Models\StripeSubscription;
use App\Support\DataTableFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class StripeSubscriptionDataTable extends DataTable
{
    public function dataTable(Builder|BaseQueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->setRowId('id')
            ->rawColumns(['customer_name', 'status', 'service_category', 'action'])
            ->filterColumn('customer_name', function (Builder $builder, string $keyword)
            {
                $builder->where('service_syncs.customer_name', 'like', '%'.$keyword.'%');
            })
            ->filterColumn('enterprise_contact_search', function (Builder $builder, string $keyword)
            {
                $like = '%'.$keyword.'%';

                $builder->where(function (Builder $q) use ($like)
                {
                    $q->whereHas('enterprise', function (Builder $eq) use ($like)
                    {
                        $eq->whereColumn('enterprises.team_id', 'service_syncs.team_id')
                            ->whereNull('enterprises.deleted_at')
                            ->where(function (Builder $inner) use ($like)
                            {
                                $inner->where('enterprises.name', 'like', $like)
                                    ->orWhereHas('contacts', function (Builder $cq) use ($like)
                                    {
                                        $cq->whereNull('contacts.deleted_at')
                                            ->where(function (Builder $c2) use ($like)
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
            ->addColumn('service_category', function (StripeSubscription $sub)
            {
                return $this->renderServiceCategoryBadge($sub);
            })
            ->addColumn('action', function (StripeSubscription $sub)
            {
                $user = auth()->user();
                if (! $user)
                {
                    return '<span class="text-muted">—</span>';
                }

                $parts = [];
                $service = $this->linkedService($sub);

                if ($service)
                {
                    $parts[] = '<a href="'.e(route('service.show', $service->id)).'" class="text-body" title="'.e(__('stripe_subscription.open_service')).'">'
                        .'<i class="ti ti-eye ti-sm"></i>'
                        .'</a>';
                } elseif (
                    $sub->getAttribute('enterprise_match_id')
                    && $user->can('create', Service::class)
                    && $user->canAccessBilling()
                ) {
                    $parts[] = '<a href="javascript:;" class="text-body create-subscription-service" data-subscription-id="'.(int) $sub->id.'" title="'.e(__('stripe_subscription.create_service')).'">'
                        .'<i class="ti ti-plus ti-sm"></i>'
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
                } elseif (filled($sub->customer_id) && $user->hasAnyRole(['admin', 'collaborator']))
                {
                    $parts[] = '<a href="'.e(route('subscription.stripe-link-client', $sub->id)).'" class="text-body" title="'.e(__('stripe_subscription.link_client')).'">'
                        .'<i class="ti ti-link ti-sm"></i>'
                        .'</a>';
                }

                if ($parts === [])
                {
                    return '<span class="text-muted">—</span>';
                }

                return '<div class="d-flex justify-content-center align-items-center gap-1">'.implode('', $parts).'</div>';
            })
            ->editColumn('customer_name', function (StripeSubscription $sub)
            {
                $label = $sub->customer_name ?: '—';
                $user = auth()->user();

                if (! $user || $label === '—')
                {
                    return e($label);
                }

                $service = $this->linkedService($sub);

                if ($service)
                {
                    return DataTableFormatter::link(route('service.show', $service->id), $label, 'fw-medium text-body');
                }

                $enterpriseId = $sub->getAttribute('enterprise_match_id');
                if ($enterpriseId)
                {
                    $enterprise = Enterprise::query()->find($enterpriseId);
                    if ($enterprise && $user->can('view', $enterprise))
                    {
                        return DataTableFormatter::link(route('client.show', $enterprise->id), $label, 'fw-medium text-body');
                    }
                }

                return e($label);
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

    public function query(StripeSubscription $model): Builder|BaseQueryBuilder
    {
        $teamId = auth()->user()->currentTeam?->id;
        $allowedStatuses = [
            'active',
            'trialing',
            'past_due',
            'unpaid',
            'incomplete',
            'incomplete_expired',
            'canceled',
            'paused',
        ];
        $selectedStatus = strtolower(trim((string) request()->query('status', '')));
        $selectedStatus = in_array($selectedStatus, $allowedStatuses, true) ? $selectedStatus : null;

        $query = $model
            ->newQuery()
            ->select('service_syncs.*')
            ->leftJoin('enterprises', function ($join)
            {
                $join->on('enterprises.code', '=', 'service_syncs.customer_id')
                    ->on('enterprises.team_id', '=', 'service_syncs.team_id')
                    ->whereNull('enterprises.deleted_at');
            })
            ->addSelect('enterprises.id as enterprise_match_id');

        return $query
            ->when($teamId, fn ($q) => $q->where('service_syncs.team_id', $teamId))
            ->when($selectedStatus !== null, fn ($q) => $q->whereRaw('LOWER(TRIM(service_syncs.status)) = ?', [$selectedStatus]))
            ->when(! $teamId, fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByRaw('enterprises.id IS NULL DESC')
            ->orderByRaw("CASE LOWER(TRIM(service_syncs.status))
                WHEN 'past_due' THEN 1
                WHEN 'unpaid' THEN 2
                WHEN 'incomplete' THEN 3
                WHEN 'incomplete_expired' THEN 4
                WHEN 'trialing' THEN 5
                WHEN 'active' THEN 6
                WHEN 'paused' THEN 7
                WHEN 'canceled' THEN 8
                ELSE 99
            END ASC")
            ->orderBy('service_syncs.customer_name');
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
            ->language(['url' => '/js/datatables/'.strtolower(substr((string) session()->get('locale', app()->getLocale()), 0, 2)).'.json'])
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
            Column::computed('service_category')
                ->title(__('stripe_subscription.columns.category'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('min-tablet')
                ->className('text-center'),
            Column::make('status')->title(__('stripe_subscription.columns.status'))->addClass('min-phone')->className('text-center')->orderable(true),
            Column::make('current_period_end')->title(__('stripe_subscription.columns.current_period_end'))->addClass('min-desktop')->className('text-center')->orderable(true),
            Column::make('amount_total')->title(__('stripe_subscription.columns.amount_total'))->addClass('min-desktop')->className('text-end')->orderable(true),
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
                ->width(96)
                ->addClass('min-desktop')
                ->className('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'StripeSubscription_'.date('YmdHis');
    }

    private function linkedService(StripeSubscription $sub): ?Service
    {
        $user = auth()->user();
        if (! $user)
        {
            return null;
        }

        return Service::withoutGlobalScopes()
            ->with('category')
            ->where('subscription_id', $sub->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->first(fn (Service $service) => $user->can('view', $service));
    }

    private function renderServiceCategoryBadge(StripeSubscription $sub): string
    {
        $service = $this->linkedService($sub);
        if (! $service)
        {
            return '<span class="text-muted">—</span>';
        }

        $user = auth()->user();
        $categoryId = $service->category_id ? (string) $service->category_id : '';
        $label = $categoryId !== ''
            ? (string) ($service->category?->name ?? '#'.$categoryId)
            : (string) __('Uncategorized');
        $hasCategory = $categoryId !== '';
        $badgeClass = $hasCategory ? 'bg-label-primary' : 'bg-label-secondary';

        $canEdit = $user
            && $user->canAccessBilling()
            && $user->can('update', $service);

        if (! $canEdit)
        {
            return '<span class="badge '.$badgeClass.'">'.e($label).'</span>';
        }

        return '<button type="button"'
            .' class="badge '.$badgeClass.' border-0 subscription-category-badge"'
            .' data-subscription-id="'.(int) $sub->id.'"'
            .' data-service-id="'.(int) $service->id.'"'
            .' data-category-id="'.e($categoryId).'">'
            .e($label)
            .'</button>';
    }
}
