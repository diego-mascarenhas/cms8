<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateSubscriptionMetadataRequest;
use App\Http\Requests\UpdateSubscriptionServiceCategoryRequest;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Services\Billing\ServiceCategoryAssignmentService;
use App\Services\Billing\ServiceSyncImporter;
use App\Services\Finance\ServiceCategoryOptionsService;
use App\Services\Hosting\HostingPlanCatalog;
use App\Services\Hosting\HostingSubscriptionSync;
use App\Services\Hosting\StripeHostingPlanGateway;
use App\Support\HostingAccountPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use RuntimeException;

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

    public function categories(Request $request, ServiceCategoryOptionsService $categories): JsonResponse
    {
        $user = $request->user();

        if (! $user?->currentTeam)
        {
            return response()->json(['success' => false, 'message' => 'Usuario no autenticado o sin equipo'], 401);
        }

        if (! $user->canAccessBilling())
        {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para ver suscripciones'], 403);
        }

        return response()->json([
            'success' => true,
            'categories' => $categories->optionsForTeam((int) $user->currentTeam->id),
        ]);
    }

    public function updateServiceCategory(
        UpdateSubscriptionServiceCategoryRequest $request,
        string $id,
        ServiceSyncImporter $importer,
        ServiceCategoryAssignmentService $assignment,
    ): JsonResponse {
        $sync = $this->findForUser($request, $id);

        if ($sync instanceof JsonResponse)
        {
            return $sync;
        }

        if ($sync->isClientHosting())
        {
            return response()->json([
                'success' => false,
                'message' => 'Las suscripciones de hosting no cambian de categoría de servicio. El plan se elige en cPanel/WHM.',
            ], 422);
        }

        $service = $importer->findLinkedService($sync);

        if (! $service)
        {
            return response()->json([
                'success' => false,
                'message' => __('No service is linked to this subscription.'),
            ], 422);
        }

        $this->authorize('update', $service);

        $categoryId = $request->input('category_id');
        $categoryId = $categoryId === null || $categoryId === '' ? null : (int) $categoryId;

        try
        {
            $service = $importer->updateServiceCategory($sync, $categoryId);
            $assignment->publish($service);
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $this->hydrateClientEnterprises(collect([$sync->fresh()->load('enterprise:id,name,code')]));

        return response()->json([
            'success' => true,
            'message' => 'La categoría quedó actualizada.',
            'service_id' => (int) $service->id,
            'category_id' => $service->category_id ? (int) $service->category_id : null,
            'category_name' => (string) ($service->category?->name ?? __('Uncategorized')),
            'data' => $this->present($sync->fresh()->load('enterprise:id,name,code'), true),
        ]);
    }

    public function updateMetadata(
        UpdateSubscriptionMetadataRequest $request,
        string $id,
        HostingSubscriptionSync $store,
        StripeHostingPlanGateway $stripe,
    ): JsonResponse {
        $sync = $this->findForUser($request, $id);

        if ($sync instanceof JsonResponse)
        {
            return $sync;
        }

        $kind = (string) $request->validated('kind');

        if ($sync->isClientHosting() && $kind !== 'hosting')
        {
            return response()->json([
                'success' => false,
                'message' => 'Esta suscripción es de hosting y no puede pasar a otro tipo de servicio.',
            ], 422);
        }

        if ($sync->isClientHosting())
        {
            $kind = 'hosting';
        }

        $metadata = $store->strings($request->validated('metadata'));
        $metadata['type'] = $kind;
        $metadata['category'] = $kind === 'hosting'
            ? 'hosting'
            : (($metadata['category'] ?? '') !== '' && $metadata['category'] !== 'hosting' ? $metadata['category'] : 'service');

        if ($this->rejectsHostingPlanMetadataChange($sync, $metadata))
        {
            return response()->json([
                'success' => false,
                'message' => 'Cambiá el plan desde el selector de hosting; debe sincronizarse con WHM.',
            ], 422);
        }

        try
        {
            $written = $stripe->replaceMetadata(
                $request->user()->currentTeam,
                (string) $sync->stripe_id,
                $store->replacement($metadata, $store->strings($this->metadata($sync))),
            );
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if (! $written)
        {
            return response()->json([
                'success' => false,
                'message' => 'Stripe no está configurado en el equipo.',
            ], 422);
        }

        $store->remember($sync, $metadata);
        $sync = $sync->fresh()->load('enterprise:id,name,code');
        $this->hydrateClientEnterprises(collect([$sync]));

        return response()->json([
            'success' => true,
            'message' => 'La metadata quedó actualizada.',
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
        $linkedService = app(ServiceSyncImporter::class)->findLinkedService($sync);
        $linkedService?->loadMissing('category:id,name');
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
            'kind' => $sync->clientKind(),
            'service_id' => $linkedService?->id,
            'service_category_id' => $linkedService?->category_id ? (int) $linkedService->category_id : null,
            'service_category_name' => $linkedService?->category?->name,
        ];

        if ($withMetadata)
        {
            $payload['metadata'] = $this->metadata($sync);
            $payload['hosting'] = $sync->isClientHosting() ? $this->hostingAccount($sync) : null;
        }

        return $payload;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function rejectsHostingPlanMetadataChange(ServiceSync $sync, array $metadata): bool
    {
        if (! $sync->isClientHosting() && ($metadata['type'] ?? '') !== 'hosting' && ($metadata['category'] ?? '') !== 'hosting')
        {
            return false;
        }

        if (! array_key_exists('plan', $metadata))
        {
            return false;
        }

        $incoming = strtolower(trim((string) $metadata['plan']));
        $currentMeta = strtolower(trim((string) data_get($this->metadata($sync), 'plan', '')));
        $domainPlan = '';

        $candidate = $sync->hostingDomainCandidate();

        if ($candidate !== null && $candidate !== '')
        {
            $domain = Domain::query()
                ->whereRaw('LOWER(domain) = ?', [$candidate])
                ->first();
            $domainPlan = strtolower(trim((string) ($domain?->plan ?? '')));
        }

        $current = $domainPlan !== '' ? $domainPlan : $currentMeta;

        return $incoming !== $current;
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

        $plans = app(HostingPlanCatalog::class);
        $data = HostingAccountPresenter::forDomain(
            $domain,
            (bool) data_get($sync->data, 'metadata.auto_suspend', data_get($sync->raw_payload, 'metadata.auto_suspend', false)),
        );
        $data['can_change_plan'] = ! $plans->isFlex($domain->plan);
        $data['plans'] = $data['can_change_plan'] ? $plans->options($domain) : [];

        return $data;
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
