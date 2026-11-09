<?php

namespace App\Services\Clients;

use App\Helpers\AvatarHelper;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Models\User;
use App\Policies\ClientPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientRoster
{
    /**
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, total: int, summary: array{clients: int, active: int, with_consumption: int, with_renewal: int}}
     */
    public function page(User $user, ?string $search, int $page, int $perPage, ?string $filter = null, ?string $status = null): array
    {
        $query = Enterprise::query()->clients()->with(['status:id,name,label_class']);
        $query = ClientPolicy::getQueryFilter($user)($query);
        $this->applyFilter($query, $user, (string) $filter);
        $this->applyStatus($query, (string) $status);

        $term = trim((string) $search);
        if ($term !== '')
        {
            $query->where('name', 'like', '%'.$term.'%');
        }

        $paginator = $query->orderBy('name')->paginate($perPage, ['*'], 'page', $page);
        $clients = $paginator->getCollection();
        $ids = $clients->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $codes = $clients->pluck('code')->filter(fn ($code): bool => filled($code))->values()->all();
        $teamId = (int) $user->currentTeam->id;

        $subscriptions = $codes === []
            ? collect()
            : ServiceSync::query()
                ->where('team_id', $teamId)
                ->whereIn('customer_id', $codes)
                ->whereNotIn('status', ['canceled', 'incomplete_expired'])
                ->get()
                ->groupBy('customer_id');

        $usages = $codes === []
            ? collect()
            : InvoiceSync::query()
                ->where('team_id', $teamId)
                ->where('billing_reason', 'manual')
                ->whereIn('customer_id', $codes)
                ->orderByDesc('invoice_created_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('customer_id');

        $serviceCounts = $ids === []
            ? collect()
            : Service::withoutGlobalScopes()
                ->whereIn('enterprise_id', $ids)
                ->whereNull('subscription_id')
                ->whereNull('deleted_at')
                ->groupBy('enterprise_id')
                ->selectRaw('enterprise_id, COUNT(*) as total')
                ->pluck('total', 'enterprise_id');

        $balances = $ids === []
            ? collect()
            : Invoice::query()
                ->where('team_id', $teamId)
                ->where('operation', 'sell')
                ->whereIn('enterprise_id', $ids)
                ->groupBy('enterprise_id')
                ->selectRaw('enterprise_id, SUM(balance) as balance, MAX(currency_id) as currency_id')
                ->get()
                ->keyBy('enterprise_id');

        $contacts = $ids === []
            ? collect()
            : Contact::query()
                ->with([
                    'status:id,name',
                    'categories:id',
                    'user:id,name,email',
                    'enterprises' => function ($query) use ($ids, $teamId)
                    {
                        $query->where('enterprises.team_id', $teamId)->whereIn('enterprises.id', $ids);
                    },
                ])
                ->where('team_id', $teamId)
                ->whereHas('enterprises', fn ($builder) => $builder->where('enterprises.team_id', $teamId)->whereIn('enterprises.id', $ids))
                ->get();

        $contactsByClient = [];
        $positionsByClient = [];
        foreach ($contacts as $contact)
        {
            if ((int) $contact->team_id !== $teamId)
            {
                continue;
            }

            foreach ($contact->enterprises as $enterprise)
            {
                if ((int) $enterprise->team_id !== $teamId || ! in_array((int) $enterprise->id, $ids, true))
                {
                    continue;
                }

                $enterpriseId = (int) $enterprise->id;
                $contactsByClient[$enterpriseId][] = $contact;
                $positionsByClient[$enterpriseId][(int) $contact->id] = trim((string) ($enterprise->pivot->position ?? ''));
            }
        }

        $staffUserIds = $this->staffUserIds(
            $teamId,
            $contacts->pluck('user_id')->filter()->map(fn ($id): int => (int) $id)->unique()->all(),
        );

        $logins = $this->lastLogins($contacts->pluck('user_id')->filter()->map(fn ($id): int => (int) $id)->unique()->all());

        $rows = $clients->map(function (Enterprise $client) use ($subscriptions, $usages, $balances, $contactsByClient, $positionsByClient, $logins, $serviceCounts, $staffUserIds): array
        {
            return $this->row($client, $subscriptions, $usages, $balances, $contactsByClient, $positionsByClient, $logins, $serviceCounts, $staffUserIds);
        })->values()->all();

        return [
            'data' => $rows,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'summary' => $this->summary($user),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, ServiceSync>>  $subscriptions
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, InvoiceSync>>  $usages
     * @param  \Illuminate\Support\Collection<int, Invoice>  $balances
     * @param  array<int, list<Contact>>  $contactsByClient
     * @param  array<int, array<int, string>>  $positionsByClient
     * @param  array<int, Carbon>  $logins
     * @param  \Illuminate\Support\Collection<int|string, int|string>  $serviceCounts
     * @param  array<int, true>  $staffUserIds
     * @return array<string, mixed>
     */
    private function row(Enterprise $client, $subscriptions, $usages, $balances, array $contactsByClient, array $positionsByClient, array $logins, $serviceCounts, array $staffUserIds): array
    {
        $people = collect($contactsByClient[(int) $client->id] ?? [])
            ->sortBy(fn (Contact $contact): string => mb_strtolower(trim($contact->name.' '.(string) ($contact->surname ?? ''))));
        $positions = $positionsByClient[(int) $client->id] ?? [];
        $administrator = $this->companyAdministrator($people, $positions, (int) $client->id);
        $primary = $administrator
            ?? $people->first(fn (Contact $contact): bool => trim((string) $contact->email) !== '')
            ?? $people->first();
        $plan = $this->plan($subscriptions->get((string) $client->code));
        $usage = $usages->get((string) $client->code)?->first();
        $balance = $balances->get((int) $client->id);
        $lastLogin = $people
            ->map(fn (Contact $contact): ?Carbon => $logins[(int) $contact->user_id] ?? null)
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'id' => (int) $client->id,
            'name' => (string) $client->name,
            'country' => trim((string) $client->country) ?: null,
            'status' => $client->status ? [
                'name' => (string) $client->status->name,
                'label_class' => (string) $client->status->label_class,
            ] : null,
            'plan' => $plan['name'],
            'amount' => $plan['amount'],
            'services_count' => (int) ($serviceCounts[(int) $client->id] ?? $serviceCounts[(string) $client->id] ?? 0),
            'renewal' => $plan['renewal'],
            'consumption' => $usage?->consumptionSummary() ?: null,
            'consumption_amount' => $usage && $usage->total !== null
                ? $this->money((float) $usage->total, (string) $usage->currency)
                : null,
            'balance' => $balance ? $this->money((float) $balance->balance, '') : null,
            'last_login' => $lastLogin?->toIso8601String(),
            'avatar_url' => $primary ? $primary->avatarUrl(40) : AvatarHelper::generate((string) $client->name, 40),
            'administrator' => $administrator ? [
                'id' => (int) $administrator->id,
                'name' => trim($administrator->name.' '.(string) ($administrator->surname ?? '')),
            ] : null,
            'contact' => $primary ? $this->contact($primary, $staffUserIds) : null,
        ];
    }

    /**
     * The company administrator is the contact with that role on this enterprise.
     * A person who administers another company can also be a client of this team,
     * so the role is never taken from the user account or from another enterprise.
     *
     * @param  \Illuminate\Support\Collection<int, Contact>  $people
     * @param  array<int, string>  $positions
     */
    private function companyAdministrator($people, array $positions, int $enterpriseId): ?Contact
    {
        $admins = $people->filter(function (Contact $contact) use ($positions): bool
        {
            return strcasecmp($positions[(int) $contact->id] ?? '', 'Administrador') === 0;
        })->values();

        if ($admins->isEmpty())
        {
            return null;
        }

        $current = $admins->filter(function (Contact $contact) use ($enterpriseId): bool
        {
            return (int) $contact->current_enterprise_id === $enterpriseId;
        })->values();

        if ($current->count() === 1)
        {
            return $current->first();
        }

        return $admins->count() === 1 ? $admins->first() : null;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, true>
     */
    private function staffUserIds(int $teamId, array $userIds): array
    {
        if ($userIds === [])
        {
            return [];
        }

        $ids = [];
        $rows = DB::table('team_user')
            ->where('team_id', $teamId)
            ->whereIn('user_id', $userIds)
            ->whereIn('role', ['admin', 'editor', 'collaborator'])
            ->pluck('user_id');

        foreach ($rows as $userId)
        {
            $ids[(int) $userId] = true;
        }

        return $ids;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Enterprise>  $query
     */
    private function applyStatus($query, string $status): void
    {
        if ($status === '1' || $status === '2')
        {
            $query->where('status_id', (int) $status);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Enterprise>  $query
     */
    private function applyFilter($query, User $user, string $filter): void
    {
        if ($filter === 'active')
        {
            $query->activeClients();

            return;
        }

        if (! in_array($filter, ['consumption', 'renewal'], true))
        {
            return;
        }

        $teamId = (int) $user->currentTeam->id;
        $codes = $filter === 'consumption'
            ? $this->customerCodes(InvoiceSync::query()->where('billing_reason', 'manual'), $teamId)
            : $this->customerCodes(
                ServiceSync::query()->whereNotIn('status', ['canceled', 'incomplete_expired'])->whereNotNull('current_period_end'),
                $teamId,
            );

        $query->whereIn('code', $codes);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<InvoiceSync|ServiceSync>  $query
     * @return list<string>
     */
    private function customerCodes($query, int $teamId): array
    {
        return $query
            ->where('team_id', $teamId)
            ->pluck('customer_id')
            ->map(fn ($code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{clients: int, active: int, with_consumption: int, with_renewal: int}
     */
    private function summary(User $user): array
    {
        $clients = Enterprise::query()->clients();
        $clients = ClientPolicy::getQueryFilter($user)($clients);
        $teamId = (int) $user->currentTeam->id;
        $codes = (clone $clients)
            ->pluck('code')
            ->map(fn ($code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'clients' => (int) (clone $clients)->count(),
            'active' => (int) (clone $clients)->activeClients()->count(),
            'with_consumption' => $this->distinctCustomers(InvoiceSync::query()->where('billing_reason', 'manual'), $teamId, $codes),
            'with_renewal' => $this->distinctCustomers(
                ServiceSync::query()->whereNotIn('status', ['canceled', 'incomplete_expired'])->whereNotNull('current_period_end'),
                $teamId,
                $codes,
            ),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<InvoiceSync|ServiceSync>  $query
     * @param  list<string>  $codes
     */
    private function distinctCustomers($query, int $teamId, array $codes): int
    {
        if ($codes === [])
        {
            return 0;
        }

        return (int) $query
            ->where('team_id', $teamId)
            ->whereIn('customer_id', $codes)
            ->selectRaw('COUNT(DISTINCT customer_id) as aggregate')
            ->value('aggregate');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ServiceSync>|null  $syncs
     * @return array{name: ?string, amount: ?string, renewal: ?string}
     */
    private function plan($syncs): array
    {
        if ($syncs === null || $syncs->isEmpty())
        {
            return ['name' => null, 'amount' => null, 'renewal' => null];
        }

        $rank = ['active' => 0, 'trialing' => 1, 'past_due' => 2, 'unpaid' => 3];
        $sync = $syncs->sortBy(function (ServiceSync $sync) use ($rank): array
        {
            return [
                $rank[strtolower(trim((string) $sync->status))] ?? 9,
                $sync->current_period_end?->timestamp ?? PHP_INT_MAX,
            ];
        })->first();

        $renewal = $sync->current_period_end;
        $name = $sync->clientFacingName();
        if (preg_match('/^(sub|cus|price)_/i', $name) === 1)
        {
            $name = '';
        }

        return [
            'name' => $name !== '' ? $name : null,
            'amount' => $sync->amount_total !== null
                ? $this->money((float) $sync->amount_total, (string) $sync->price_currency)
                : null,
            'renewal' => $renewal instanceof Carbon ? $renewal->toDateString() : null,
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, Carbon>
     */
    private function lastLogins(array $userIds): array
    {
        if ($userIds === [])
        {
            return [];
        }

        $logins = [];
        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->whereNotNull('last_used_at')
            ->selectRaw('tokenable_id, MAX(last_used_at) as last_used_at')
            ->groupBy('tokenable_id')
            ->get();

        foreach ($tokens as $token)
        {
            $logins[(int) $token->tokenable_id] = Carbon::parse($token->last_used_at);
        }

        if (Schema::hasTable('sessions'))
        {
            $sessions = DB::table('sessions')
                ->whereIn('user_id', $userIds)
                ->selectRaw('user_id, MAX(last_activity) as last_activity')
                ->groupBy('user_id')
                ->get();

            foreach ($sessions as $session)
            {
                $at = Carbon::createFromTimestamp((int) $session->last_activity);
                $current = $logins[(int) $session->user_id] ?? null;
                if ($current === null || $at->greaterThan($current))
                {
                    $logins[(int) $session->user_id] = $at;
                }
            }
        }

        return $logins;
    }

    /**
     * @param  array<int, true>  $staffUserIds
     * @return array<string, mixed>
     */
    private function contact(Contact $contact, array $staffUserIds): array
    {
        $name = trim($contact->name.' '.(string) ($contact->surname ?? ''));

        return [
            'id' => (int) $contact->id,
            'name' => (string) $contact->name,
            'surname' => trim((string) ($contact->surname ?? '')) ?: null,
            'display_name' => $name !== '' ? $name : (string) $contact->name,
            'email' => (string) ($contact->email ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
            'status' => $contact->status ? ['id' => (int) $contact->status->id] : null,
            'categories' => $contact->categories->map(fn ($category): array => ['id' => (int) $category->id])->values()->all(),
            'user' => $contact->user ? [
                'id' => (int) $contact->user->id,
                'name' => (string) $contact->user->name,
                'email' => (string) $contact->user->email,
                'staff' => isset($staffUserIds[(int) $contact->user->id]),
            ] : null,
        ];
    }

    private function money(float $amount, string $currency): string
    {
        $code = strtoupper(trim($currency));

        return trim(number_format($amount, 2, ',', '.').($code !== '' ? ' '.$code : ''));
    }
}
