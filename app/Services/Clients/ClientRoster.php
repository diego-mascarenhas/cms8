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
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, total: int}
     */
    public function page(User $user, ?string $search, int $page, int $perPage): array
    {
        $query = Enterprise::query()->clients()->with('status:id,name,label_class');
        $query = ClientPolicy::getQueryFilter($user)($query);

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
                ->with(['status:id,name', 'categories:id', 'user:id,name,email', 'enterprises:id'])
                ->whereHas('enterprises', fn ($builder) => $builder->whereIn('enterprises.id', $ids))
                ->get();

        $contactsByClient = [];
        foreach ($contacts as $contact)
        {
            foreach ($contact->enterprises as $enterprise)
            {
                $contactsByClient[(int) $enterprise->id][] = $contact;
            }
        }

        $logins = $this->lastLogins($contacts->pluck('user_id')->filter()->map(fn ($id): int => (int) $id)->unique()->all());

        $rows = $clients->map(function (Enterprise $client) use ($subscriptions, $usages, $balances, $contactsByClient, $logins, $serviceCounts): array
        {
            return $this->row($client, $subscriptions, $usages, $balances, $contactsByClient, $logins, $serviceCounts);
        })->values()->all();

        return [
            'data' => $rows,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, ServiceSync>>  $subscriptions
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, InvoiceSync>>  $usages
     * @param  \Illuminate\Support\Collection<int, Invoice>  $balances
     * @param  array<int, list<Contact>>  $contactsByClient
     * @param  array<int, Carbon>  $logins
     * @param  \Illuminate\Support\Collection<int|string, int|string>  $serviceCounts
     * @return array<string, mixed>
     */
    private function row(Enterprise $client, $subscriptions, $usages, $balances, array $contactsByClient, array $logins, $serviceCounts): array
    {
        $people = collect($contactsByClient[(int) $client->id] ?? [])
            ->sortBy(fn (Contact $contact): string => mb_strtolower(trim($contact->name.' '.(string) ($contact->surname ?? ''))));
        $primary = $people->first(fn (Contact $contact): bool => trim((string) $contact->email) !== '') ?? $people->first();
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
            'contact' => $primary ? $this->contact($primary) : null,
        ];
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
     * @return array<string, mixed>
     */
    private function contact(Contact $contact): array
    {
        $name = trim($contact->name.' '.(string) ($contact->surname ?? ''));

        return [
            'id' => (int) $contact->id,
            'name' => (string) $contact->name,
            'display_name' => $name !== '' ? $name : (string) $contact->name,
            'email' => (string) ($contact->email ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
            'status' => $contact->status ? ['id' => (int) $contact->status->id] : null,
            'categories' => $contact->categories->map(fn ($category): array => ['id' => (int) $category->id])->values()->all(),
            'user' => $contact->user ? [
                'id' => (int) $contact->user->id,
                'name' => (string) $contact->user->name,
                'email' => (string) $contact->user->email,
            ] : null,
        ];
    }

    private function money(float $amount, string $currency): string
    {
        $code = strtoupper(trim($currency));

        return trim(number_format($amount, 2, ',', '.').($code !== '' ? ' '.$code : ''));
    }
}
