<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeHostingPlanRequest;
use App\Http\Requests\StoreApiHostingRequest;
use App\Http\Requests\UpdateHostingEmailPasswordRequest;
use App\Http\Requests\UpdateHostingEmailQuotaRequest;
use App\Http\Requests\UpdateHostingPasswordRequest;
use App\Models\Domain;
use App\Models\Server;
use App\Services\Hosting\DomainCpanelPasswordService;
use App\Services\Hosting\DomainMailboxPasswordService;
use App\Services\Hosting\DomainMailboxQuotaService;
use App\Services\Hosting\DomainRuntimeDetector;
use App\Services\Hosting\FlexHostingProvisioner;
use App\Services\Hosting\HostingFailureNotice;
use App\Services\Hosting\HostingPlanCatalog;
use App\Services\Hosting\HostingSubscriptionSync;
use App\Support\HostingAccountPresenter;
use App\Support\MailExchange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class HostingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->canAccessInfrastructure())
        {
            abort(403);
        }

        $accounts = $this->teamAccounts($request);
        $total = (clone $accounts)->count();
        $active = (clone $accounts)->where('suspended', false)->count();
        $suspended = (clone $accounts)->where('suspended', true)->count();

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        if ($search !== '')
        {
            $accounts->where(function (Builder $builder) use ($search)
            {
                $builder->where('domain', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('plan', 'like', '%'.$search.'%');
            });
        }

        if ($status === 'active')
        {
            $accounts->where('suspended', false);
        } elseif ($status === 'suspended')
        {
            $accounts->where('suspended', true);
        }

        $perPage = min(50, max(1, (int) $request->query('per_page', 25)));
        $page = $accounts->orderBy('domain')->paginate($perPage);

        return response()->json([
            'success' => true,
            'nameservers' => $this->provisioningNameservers(),
            'stats' => [
                'total' => $total,
                'active' => $active,
                'suspended' => $suspended,
            ],
            'data' => $page->through(fn (Domain $domain) => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'username' => $domain->username,
                'plan' => $domain->plan,
                'site_type' => $domain->site_type,
                'php_version' => $domain->php_version,
                'suspended' => (bool) $domain->suspended,
                'server' => $domain->server?->name,
            ]),
        ]);
    }

    public function show(Request $request, Domain $domain, HostingPlanCatalog $plans, DomainRuntimeDetector $runtime): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $runtime->fillMissing($domain);

        return response()->json([
            'success' => true,
            'data' => $this->account($domain, $plans),
        ]);
    }

    public function updatePlan(ChangeHostingPlanRequest $request, Domain $domain, HostingPlanCatalog $plans, HostingSubscriptionSync $subscriptions): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $result = $plans->change($domain, (string) $request->validated('plan'));

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        $domain = $domain->fresh();
        $note = $subscriptions->afterPlanChange($request->user()->currentTeam, $domain);
        $message = trim($result['message'].($note !== '' ? ' '.$note : ''));

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->account($domain, $plans),
        ]);
    }

    public function updatePassword(UpdateHostingPasswordRequest $request, Domain $domain, DomainCpanelPasswordService $passwords): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $result = $passwords->resetAndOptionallyNotify(
            $request->user(),
            $domain,
            null,
            DomainCpanelPasswordService::NOTIFY_NONE,
            (string) $request->validated('password'),
        );

        return $this->passwordResponse($result);
    }

    public function updateEmailPassword(UpdateHostingEmailPasswordRequest $request, Domain $domain, DomainMailboxPasswordService $mailboxes): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $email = (string) $request->validated('email');
        $suffix = '@'.strtolower((string) $domain->domain);

        if (! str_ends_with($email, $suffix))
        {
            return response()->json([
                'success' => false,
                'message' => 'Esa cuenta no pertenece a este dominio.',
            ], 422);
        }

        $result = $mailboxes->resetAndOptionallyNotify(
            $request->user(),
            $domain,
            $email,
            null,
            DomainCpanelPasswordService::NOTIFY_NONE,
            (string) $request->validated('password'),
        );

        return $this->passwordResponse($result);
    }

    public function updateEmailQuota(UpdateHostingEmailQuotaRequest $request, Domain $domain, DomainMailboxQuotaService $quotas): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $email = (string) $request->validated('email');
        $suffix = '@'.strtolower((string) $domain->domain);

        if (! str_ends_with($email, $suffix))
        {
            return response()->json([
                'success' => false,
                'message' => 'Esa cuenta no pertenece a este dominio.',
            ], 422);
        }

        $quota = $request->validated('quota_mb');
        $result = $quotas->increase(
            $domain,
            $email,
            is_numeric($quota) ? (int) $quota : null,
            (bool) $request->boolean('unlimited'),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    public function store(StoreApiHostingRequest $request, FlexHostingProvisioner $provisioner, HostingFailureNotice $failures): JsonResponse
    {
        $server = $this->provisioningServer();
        $context = [
            'domain' => (string) $request->validated('domain'),
            'username' => (string) $request->validated('username'),
            'user' => $request->user()?->email,
        ];

        if ($server === null)
        {
            $message = 'No hay un servidor cPanel configurado para el equipo.';
            $failures->report($message, $context);

            return response()->json([
                'success' => false,
                'message' => $failures->userMessage($message),
            ], 422);
        }

        $provider = (string) $request->validated('mail_provider');
        $mxRecords = MailExchange::records($provider, (string) $request->validated('mail_exchange', ''));
        $mailboxes = $mxRecords === [] ? array_values($request->validated('mailboxes')) : [];

        try
        {
            $created = $provisioner->provision(
                $request->user(),
                $server,
                (int) $request->validated('enterprise_id'),
                (string) $request->validated('domain'),
                (string) $request->validated('username'),
                $mailboxes,
                $mxRecords,
            );
        } catch (RuntimeException $exception)
        {
            $technical = $exception->getMessage();
            $failures->report($technical, $context);

            return response()->json([
                'success' => false,
                'message' => $failures->userMessage($technical),
            ], 422);
        }

        $createdMailboxes = $created['mailboxes'];

        foreach ($createdMailboxes as $index => $mailbox)
        {
            if (($mailbox['created'] ?? false) || ! filled($mailbox['error'] ?? null))
            {
                continue;
            }

            $technical = (string) $mailbox['error'];
            $failures->report($technical, $context + ['mailbox' => $mailbox['email'] ?? null]);
            $createdMailboxes[$index]['error'] = $failures->userMessage($technical, 'No se pudo crear el correo. Avisamos al equipo para resolverlo.');
        }

        if (filled($created['mail_dns_error'] ?? null))
        {
            $technical = (string) $created['mail_dns_error'];
            $failures->report($technical, $context);
            $created['mail_dns_error'] = $failures->userMessage($technical, 'El hosting quedó creado, pero no se pudo preparar el correo. Avisamos al equipo para resolverlo.');
        }

        return response()->json([
            'success' => true,
            'plan' => $created['plan'],
            'data' => [
                'id' => $created['domain']->id,
                'domain' => $created['domain']->domain,
                'username' => $created['domain']->username,
                'password' => $created['password'],
                'nameservers' => $created['nameservers'],
                'spf_configured' => $created['spf_configured'],
                'mailboxes' => $createdMailboxes,
                'mail_dns_configured' => $created['mail_dns_configured'],
                'mail_dns_error' => $created['mail_dns_error'],
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function account(Domain $domain, HostingPlanCatalog $plans): array
    {
        $data = HostingAccountPresenter::forDomain(
            $domain,
            (bool) data_get($domain->data, 'auto_suspend', false),
        );
        $data['can_change_plan'] = ! $plans->isFlex($domain->plan);
        $data['plans'] = $data['can_change_plan'] ? $plans->options($domain) : [];

        return $data;
    }

    /**
     * @return list<string>
     */
    private function provisioningNameservers(): array
    {
        $nameservers = $this->provisioningServer()?->getProvisioningNameservers() ?: config('humano_hosting.default_nameservers', []);

        return array_values(array_filter($nameservers, fn (mixed $name) => is_string($name) && $name !== ''));
    }

    private function provisioningServer(): ?Server
    {
        return Server::query()
            ->where('control_panel', 'cpanel')
            ->orderBy('id')
            ->get()
            ->first(fn (Server $server) => $server->hasToken());
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function passwordResponse(array $result): JsonResponse
    {
        if (! ($result['success'] ?? false))
        {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'No se pudo actualizar la contraseña.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'password' => $result['password'] ?? null,
        ]);
    }

    private function teamDomain(Request $request, Domain $domain): Domain
    {
        $user = $request->user();

        if ($user === null || ! $user->canAccessInfrastructure())
        {
            abort(403);
        }

        $domain->load('server');
        $teamId = $user->currentTeam?->id;

        if ($teamId === null || (int) $domain->server?->team_id !== (int) $teamId)
        {
            abort(404);
        }

        return $domain;
    }

    private function teamAccounts(Request $request): Builder
    {
        $teamId = $request->user()?->currentTeam?->id;

        return Domain::query()
            ->with('server:id,name')
            ->when($teamId, function (Builder $builder) use ($teamId)
            {
                $builder->whereHas('server', function (Builder $server) use ($teamId)
                {
                    $server->where('team_id', $teamId);
                });
            });
    }
}
