<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiHostingRequest;
use App\Models\Domain;
use App\Models\Server;
use App\Services\Hosting\FlexHostingProvisioner;
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

    public function store(StoreApiHostingRequest $request, FlexHostingProvisioner $provisioner): JsonResponse
    {
        $server = $this->provisioningServer();

        if ($server === null)
        {
            return response()->json([
                'success' => false,
                'message' => 'No hay un servidor cPanel configurado para el equipo.',
            ], 422);
        }

        $mailboxes = array_values($request->validated('mailboxes'));

        try
        {
            $created = $provisioner->provision(
                $request->user(),
                $server,
                (int) $request->validated('enterprise_id'),
                (string) $request->validated('domain'),
                (string) $request->validated('username'),
                $mailboxes,
            );
        } catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
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
                'mailboxes' => $created['mailboxes'],
            ],
        ], 201);
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
