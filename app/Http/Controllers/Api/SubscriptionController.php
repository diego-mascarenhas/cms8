<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Support\HostingAccountPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json(['success' => false, 'error' => 'Usuario no autenticado o sin equipo'], 401);
        }

        if (! $user->canAccessBilling())
        {
            return response()->json(['success' => false, 'error' => 'No tienes permisos para ver suscripciones'], 403);
        }

        $statuses = ['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'incomplete_expired', 'canceled', 'paused'];
        $selectedStatus = strtolower(trim((string) $request->query('status', '')));

        if (! in_array($selectedStatus, $statuses, true))
        {
            $selectedStatus = '';
        }

        $search = trim((string) $request->query('search', ''));
        $selectedCategory = trim((string) $request->query('category', ''));
        $perPage = min(100, max(10, (int) $request->integer('per_page', 25)));
        $like = '%'.mb_strtolower($search).'%';
        $labels = $this->categoryLabels($user->currentTeam->id);
        $categoryIds = $selectedCategory === ''
            ? null
            : $labels->filter(fn (string $label): bool => $label === $selectedCategory)->keys()->all();

        $subscriptions = ServiceSync::query()
            ->with('enterprise:id,name,code')
            ->where('team_id', $user->currentTeam->id)
            ->when(is_array($categoryIds), function ($query) use ($categoryIds)
            {
                if ($categoryIds === [])
                {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereIn('id', $categoryIds);
            })
            ->when($selectedStatus !== '', function ($query) use ($selectedStatus)
            {
                $query->whereRaw('LOWER(TRIM(status)) = ?', [$selectedStatus]);
            })
            ->when($search !== '', function ($query) use ($like)
            {
                $query->where(function ($inner) use ($like)
                {
                    $inner->whereRaw('LOWER(COALESCE(customer_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(plan_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(customer_email, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(stripe_id, \'\')) LIKE ?', [$like])
                        ->orWhereHas('enterprise', function ($enterprise) use ($like)
                        {
                            $enterprise->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereHas('contacts', function ($contact) use ($like)
                                {
                                    $contact->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like])
                                        ->orWhereRaw('LOWER(COALESCE(surname, \'\')) LIKE ?', [$like]);
                                });
                        });
                });
            })
            ->orderByRaw('CASE WHEN EXISTS (
                SELECT 1 FROM enterprises
                WHERE enterprises.code = service_syncs.customer_id
                  AND enterprises.team_id = service_syncs.team_id
                  AND enterprises.deleted_at IS NULL
            ) THEN 1 ELSE 0 END')
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
            END")
            ->orderBy('customer_name')
            ->paginate($perPage);

        $this->hydrateClientEnterprises($subscriptions->getCollection());
        $subscriptions->through(fn (ServiceSync $sync) => $this->present($sync));

        return response()->json([
            'success' => true,
            'status' => $selectedStatus,
            'category' => $selectedCategory,
            'categories' => $this->categoryOptions($labels),
            'status_counts' => $this->statusCounts($user->currentTeam->id, $statuses),
            'status_totals' => $this->statusTotals($user->currentTeam->id, $statuses),
            'data' => $subscriptions,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $sync = $this->findForUser($request, $id);

        if ($sync instanceof JsonResponse)
        {
            return $sync;
        }

        $this->hydrateClientEnterprises(collect([$sync]));

        return response()->json([
            'success' => true,
            'data' => $this->present($sync, true),
        ]);
    }

    public function forEnterprise(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json(['success' => false, 'error' => 'Usuario no autenticado o sin equipo'], 401);
        }

        $enterprise = Enterprise::query()->whereKey($id)->first();

        if (! $enterprise)
        {
            return response()->json(['success' => false, 'error' => 'Empresa no encontrada'], 404);
        }

        $linkedIds = Service::query()
            ->where('enterprise_id', $enterprise->id)
            ->whereNotNull('subscription_id')
            ->pluck('subscription_id');

        $subscriptions = ServiceSync::query()
            ->where('team_id', $user->currentTeam->id)
            ->where(function ($query) use ($enterprise, $linkedIds)
            {
                $query->whereIn('id', $linkedIds);

                if (filled($enterprise->code))
                {
                    $query->orWhere('customer_id', $enterprise->code);
                }

                if (filled($enterprise->email))
                {
                    $query->orWhere('customer_email', $enterprise->email);
                }
            })
            ->orderByDesc('current_period_end')
            ->get()
            ->map(fn (ServiceSync $sync) => $this->present($sync, true))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    private function findForUser(Request $request, string $id): ServiceSync|JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json(['success' => false, 'error' => 'Usuario no autenticado o sin equipo'], 401);
        }

        if (! $user->canAccessBilling())
        {
            return response()->json(['success' => false, 'error' => 'No tienes permisos para ver suscripciones'], 403);
        }

        $sync = ServiceSync::query()
            ->with('enterprise:id,name,code')
            ->where('team_id', $user->currentTeam->id)
            ->whereKey($id)
            ->first();

        if (! $sync)
        {
            return response()->json(['success' => false, 'error' => 'Suscripción no encontrada'], 404);
        }

        return $sync;
    }

    /**
     * @return Collection<int, string>
     */
    private function categoryLabels(int $teamId): Collection
    {
        return ServiceSync::query()
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ServiceSync $sync): array => [$sync->id => $sync->clientCategoryLabel()]);
    }

    /**
     * @param  Collection<int, string>  $labels
     * @return list<array{name: string, count: int}>
     */
    private function categoryOptions(Collection $labels): array
    {
        $order = ['Hosting', 'Dominios', 'Certificados', 'Email', 'Asesoramiento', 'Otros'];
        $counts = $labels->countBy();

        return $counts
            ->keys()
            ->sortBy(function (string $name) use ($order): string
            {
                $index = array_search($name, $order, true);

                return sprintf('%03d-%s', $index === false ? 100 : $index, $name);
            })
            ->values()
            ->map(fn (string $name): array => [
                'name' => $name,
                'count' => (int) $counts[$name],
            ])
            ->all();
    }

    /**
     * @param  list<string>  $statuses
     * @return array<string, int>
     */
    private function statusCounts(int $teamId, array $statuses): array
    {
        $rows = ServiceSync::query()
            ->where('team_id', $teamId)
            ->selectRaw('LOWER(TRIM(status)) as status_key, COUNT(*) as total')
            ->groupByRaw('LOWER(TRIM(status))')
            ->get();

        $counts = array_fill_keys($statuses, 0);

        foreach ($rows as $row)
        {
            $key = (string) $row->status_key;

            if (array_key_exists($key, $counts))
            {
                $counts[$key] = (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * Sum of subscription amounts for each status, split by currency.
     *
     * @param  list<string>  $statuses
     * @return array<string, list<array{currency: string, amount: float}>>
     */
    private function statusTotals(int $teamId, array $statuses): array
    {
        $rows = ServiceSync::query()
            ->where('team_id', $teamId)
            ->selectRaw("LOWER(TRIM(status)) as status_key, UPPER(COALESCE(NULLIF(TRIM(price_currency), ''), 'EUR')) as currency, SUM(amount_total) as amount")
            ->groupByRaw("LOWER(TRIM(status)), UPPER(COALESCE(NULLIF(TRIM(price_currency), ''), 'EUR'))")
            ->get();

        $totals = array_fill_keys($statuses, []);

        foreach ($rows as $row)
        {
            $key = (string) $row->status_key;

            if (! array_key_exists($key, $totals))
            {
                continue;
            }

            $totals[$key][] = [
                'currency' => (string) $row->currency,
                'amount' => round((float) $row->amount, 2),
            ];
        }

        return $totals;
    }

    /**
     * Fill enterprise when the Stripe customer code is not stored on the company.
     *
     * @param  Collection<int, ServiceSync>  $syncs
     */
    private function hydrateClientEnterprises(Collection $syncs): void
    {
        $missing = $syncs->filter(fn (ServiceSync $sync): bool => $sync->enterprise === null)->values();

        if ($missing->isEmpty())
        {
            return;
        }

        $teamId = (int) $missing->first()->team_id;
        $chosen = $this->clientEnterpriseIds($missing, $teamId);

        if ($chosen === [])
        {
            return;
        }

        $enterprises = Enterprise::query()
            ->whereIn('id', array_values($chosen))
            ->get(['id', 'name', 'code'])
            ->keyBy('id');

        foreach ($missing as $sync)
        {
            $enterprise = $enterprises->get($chosen[$sync->id] ?? 0);

            if ($enterprise)
            {
                $sync->setRelation('enterprise', $enterprise);
            }
        }
    }

    /**
     * @param  Collection<int, ServiceSync>  $missing
     * @return array<int, int>
     */
    private function clientEnterpriseIds(Collection $missing, int $teamId): array
    {
        $fromService = Service::withoutGlobalScope('ownership')
            ->whereIn('subscription_id', $missing->pluck('id'))
            ->whereNotNull('enterprise_id')
            ->pluck('enterprise_id', 'subscription_id');

        $emails = $missing
            ->map(fn (ServiceSync $sync): string => mb_strtolower(trim((string) $sync->customer_email)))
            ->filter()
            ->unique()
            ->values();
        $names = $missing
            ->map(fn (ServiceSync $sync): string => mb_strtolower(trim((string) $sync->customer_name)))
            ->filter()
            ->unique()
            ->values();

        $byEmail = $this->enterprisesKeyedBy($teamId, 'email', $emails);
        $byName = $this->enterprisesKeyedBy($teamId, 'name', $names);
        $contactsByEmail = $emails->isEmpty()
            ? collect()
            : Contact::withoutGlobalScope('ownership')
                ->where('team_id', $teamId)
                ->where(function ($query) use ($emails): void
                {
                    foreach ($emails as $email)
                    {
                        $query->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                    }
                })
                ->with(['enterprises:id,name,code', 'currentEnterprise:id,name,code'])
                ->get()
                ->groupBy(fn (Contact $contact): string => mb_strtolower(trim((string) $contact->email)));

        $chosen = [];

        foreach ($missing as $sync)
        {
            $email = mb_strtolower(trim((string) $sync->customer_email));
            $name = mb_strtolower(trim((string) $sync->customer_name));
            $enterpriseId = (int) ($fromService[$sync->id] ?? 0);

            if ($enterpriseId === 0 && $email !== '' && $byEmail->has($email))
            {
                $enterpriseId = (int) $byEmail->get($email)->id;
            }

            if ($enterpriseId === 0 && $name !== '' && $byName->has($name))
            {
                $enterpriseId = (int) $byName->get($name)->id;
            }

            if ($enterpriseId === 0)
            {
                $enterpriseId = (int) ($this->enterpriseFromContacts($contactsByEmail->get($email), $name)?->id ?? 0);
            }

            if ($enterpriseId > 0)
            {
                $chosen[(int) $sync->id] = $enterpriseId;
            }
        }

        return $chosen;
    }

    /**
     * @param  Collection<int, string>  $values
     * @return Collection<string, Enterprise>
     */
    private function enterprisesKeyedBy(int $teamId, string $column, Collection $values): Collection
    {
        if ($values->isEmpty() || ! in_array($column, ['email', 'name'], true))
        {
            return collect();
        }

        return Enterprise::query()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($column, $values): void
            {
                foreach ($values as $value)
                {
                    $query->orWhereRaw('LOWER(TRIM('.$column.')) = ?', [$value]);
                }
            })
            ->get(['id', 'name', 'code', 'email'])
            ->keyBy(fn (Enterprise $enterprise): string => mb_strtolower(trim((string) $enterprise->{$column})));
    }

    /**
     * @param  Collection<int, Contact>|null  $contacts
     */
    private function enterpriseFromContacts(?Collection $contacts, string $customerName): ?Enterprise
    {
        if ($contacts === null || $contacts->isEmpty())
        {
            return null;
        }

        $enterprises = $contacts
            ->flatMap(function (Contact $contact): Collection
            {
                $linked = $contact->enterprises;

                if ($contact->currentEnterprise)
                {
                    $linked->push($contact->currentEnterprise);
                }

                return $linked;
            })
            ->unique('id')
            ->values();

        if ($enterprises->isEmpty())
        {
            return null;
        }

        if ($customerName !== '')
        {
            $named = $enterprises->first(
                fn (Enterprise $enterprise): bool => mb_strtolower(trim((string) $enterprise->name)) === $customerName,
            );

            if ($named)
            {
                return $named;
            }
        }

        $currentId = (int) $contacts->pluck('current_enterprise_id')->filter()->first();
        $current = $enterprises->firstWhere('id', $currentId);

        return $current ?: $enterprises->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ServiceSync $sync, bool $withMetadata = false): array
    {
        $payload = [
            'id' => $sync->id,
            'stripe_id' => $sync->stripe_id,
            'name' => $sync->clientFacingName(),
            'status' => $sync->status,
            'plan_name' => $sync->plan_name,
            'interval' => $sync->billing_frequency,
            'quantity' => $sync->quantity,
            'currency' => $sync->price_currency,
            'amount' => $sync->amount_total,
            'customer_name' => $sync->customer_name,
            'customer_email' => $sync->customer_email,
            'enterprise' => $sync->relationLoaded('enterprise') && $sync->enterprise ? [
                'id' => $sync->enterprise->id,
                'name' => $sync->enterprise->name,
            ] : null,
            'current_period_start' => $sync->current_period_start?->toDateString(),
            'current_period_end' => $sync->current_period_end?->toDateString(),
            'cancel_at_period_end' => (bool) $sync->cancel_at_period_end,
            'category' => $sync->clientCategoryLabel(),
        ];

        if ($withMetadata)
        {
            $payload['metadata'] = $this->metadata($sync);
            $payload['hosting'] = $sync->isClientHosting() ? $this->hostingAccount($sync) : null;
        }

        return $payload;
    }

    /**
     * cPanel account linked to a hosting subscription. Cached domain data only.
     *
     * @return array<string, mixed>|null
     */
    private function hostingAccount(ServiceSync $sync): ?array
    {
        $candidate = $sync->hostingDomainCandidate();
        if ($candidate === null || $candidate === '')
        {
            return null;
        }

        $domain = Domain::query()
            ->with('server')
            ->whereRaw('LOWER(domain) = ?', [$candidate])
            ->first();

        if (! $domain)
        {
            return null;
        }

        return HostingAccountPresenter::forDomain(
            $domain,
            (bool) data_get($sync->data, 'metadata.auto_suspend', data_get($sync->raw_payload, 'metadata.auto_suspend', false)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(ServiceSync $sync): array
    {
        $fromData = data_get($sync->data, 'metadata');
        $fromRaw = data_get($sync->raw_payload, 'metadata');
        $meta = is_array($fromData) && $fromData !== [] ? $fromData : (is_array($fromRaw) ? $fromRaw : []);

        foreach (['category', 'domain', 'type'] as $key)
        {
            if (! array_key_exists($key, $meta) && filled(data_get($sync->data, $key)))
            {
                $meta[$key] = data_get($sync->data, $key);
            }
        }

        return $meta;
    }
}
