<?php

namespace App\Http\Controllers;

use App\DataTables\TeamPasswordDataTable;
use App\Http\Requests\Password\CreateTeamPasswordShareRequest;
use App\Http\Requests\Password\StoreTeamPasswordRequest;
use App\Http\Requests\Password\UnlockTeamPasswordsRequest;
use App\Http\Requests\Password\UpdateTeamPasswordRequest;
use App\Models\Enterprise;
use App\Models\TeamPassword;
use App\Models\TeamPasswordShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamPasswordController extends Controller
{
    public function index(TeamPasswordDataTable $dataTable)
    {
        $team = auth()->user()->currentTeam;
        $this->assertPasswordsModuleEnabled();
        $this->authorize('viewAny', TeamPassword::class);

        if (! $team?->hasPasswordsMasterKey())
        {
            return redirect()
                ->route('team-settings.passwords', $team)
                ->with('error', __('Configura tu clave maestra antes de usar el cofre.'));
        }

        $enterprises = Enterprise::query()->orderBy('name')->pluck('name', 'id');

        return $dataTable->render('password.index', compact('enterprises'));
    }

    public function create()
    {
        $this->assertPasswordsModuleEnabled();
        $this->authorize('create', TeamPassword::class);

        $enterprises = Enterprise::query()->orderBy('name')->pluck('name', 'id');

        return view('password.form', [
            'data' => new TeamPassword,
            'enterprises' => $enterprises,
        ]);
    }

    public function store(StoreTeamPasswordRequest $request)
    {
        $this->assertPasswordsModuleEnabled();

        $payload = $request->validated();
        $teamPassword = new TeamPassword;
        $teamPassword->fill([
            'name' => $payload['name'],
            'username' => $payload['username'] ?? null,
            'password_encrypted' => filled($payload['password'] ?? null)
                ? Crypt::encryptString((string) $payload['password'])
                : null,
            'url' => $payload['url'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'enterprise_id' => $payload['enterprise_id'] ?? null,
        ]);
        $teamPassword->save();

        return redirect()->route('passwords.index')->with('success', __('Contraseña creada correctamente.'));
    }

    public function edit(TeamPassword $team_password)
    {
        $this->assertPasswordsModuleEnabled();
        $this->authorize('update', $team_password);

        $enterprises = Enterprise::query()->orderBy('name')->pluck('name', 'id');

        return view('password.form', [
            'data' => $team_password,
            'enterprises' => $enterprises,
        ]);
    }

    public function update(UpdateTeamPasswordRequest $request, TeamPassword $team_password)
    {
        $this->assertPasswordsModuleEnabled();
        $this->authorize('update', $team_password);

        $payload = $request->validated();
        $team_password->name = $payload['name'];
        $team_password->username = $payload['username'] ?? null;
        $team_password->url = $payload['url'] ?? null;
        $team_password->notes = $payload['notes'] ?? null;
        $team_password->enterprise_id = $payload['enterprise_id'] ?? null;
        if (filled($payload['password'] ?? null))
        {
            $team_password->password_encrypted = Crypt::encryptString((string) $payload['password']);
        }

        $team_password->save();

        return redirect()->route('passwords.index')->with('success', __('Contraseña actualizada correctamente.'));
    }

    public function destroy(TeamPassword $team_password)
    {
        $this->assertPasswordsModuleEnabled();
        $this->authorize('delete', $team_password);
        $team_password->delete();

        return redirect()->route('passwords.index')->with('success', __('Contraseña eliminada correctamente.'));
    }

    public function unlockForm()
    {
        $this->assertPasswordsModuleEnabled();

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            abort(403);
        }

        return view('password.unlock', [
            'team' => $team,
            'masterKeyHint' => (string) $team->getSetting('passwords_master_key_hint', ''),
        ]);
    }

    public function unlock(UnlockTeamPasswordsRequest $request)
    {
        $this->assertPasswordsModuleEnabled();
        $team = $request->user()->currentTeam;

        if (! $team || ! $team->verifyPasswordsMasterKey((string) $request->validated()['master_key']))
        {
            if ($request->expectsJson())
            {
                return response()->json(['message' => __('Clave maestra inválida.')], 422);
            }

            return redirect()->back()->withErrors(['master_key' => __('Clave maestra inválida.')]);
        }

        $request->session()->put("passwords_unlocked_team_{$team->id}", true);
        $request->session()->put("passwords_unlocked_until_team_{$team->id}", now()->addMinutes(15)->timestamp);

        if ($request->expectsJson())
        {
            return response()->json(['success' => true, 'message' => __('Cofre desbloqueado por 15 minutos.')]);
        }

        return redirect()->route('passwords.index')->with('success', __('Cofre desbloqueado por 15 minutos.'));
    }

    public function lock(Request $request)
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            abort(403);
        }

        $request->session()->forget("passwords_unlocked_team_{$team->id}");
        $request->session()->forget("passwords_unlocked_until_team_{$team->id}");

        return redirect()->route('passwords.unlock.form')->with('success', __('Cofre bloqueado.'));
    }

    public function reveal(TeamPassword $team_password)
    {
        $this->assertPasswordsModuleEnabled();
        $this->authorize('view', $team_password);
        if (! $this->isVaultUnlocked())
        {
            return response()->json([
                'requires_unlock' => true,
                'message' => __('Desbloquea el cofre para ver contraseñas.'),
            ], 423);
        }

        $plainPassword = $team_password->getPasswordPlainText();
        if (! filled($plainPassword))
        {
            return response()->json([
                'password' => null,
                'message' => __('Este registro no tiene contraseña guardada.'),
            ], 422);
        }

        return response()->json(['password' => $plainPassword]);
    }

    public function createShare(CreateTeamPasswordShareRequest $request, TeamPassword $team_password)
    {
        $this->assertPasswordsModuleEnabled();
        $this->authorize('view', $team_password);
        if (! $this->isVaultUnlocked())
        {
            return response()->json([
                'requires_unlock' => true,
                'message' => __('Desbloquea el cofre para crear enlaces compartidos.'),
            ], 423);
        }

        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        TeamPasswordShare::query()->create([
            'team_password_id' => $team_password->id,
            'token_hash' => $tokenHash,
            'max_views' => 1,
            'views_count' => 0,
            'expires_at' => now()->addDays(7),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'url' => route('passwords.share.consume', ['token' => $plainToken]),
            'expires_at' => now()->addDays(7)->toDateTimeString(),
        ]);
    }

    public function showPasswordShare(string $token)
    {
        $resolution = $this->resolvePasswordShareForPublic($token);

        return match ($resolution['status'])
        {
            'not_found' => response()->view('password.public-share', ['status' => 'not_found'], 404),
            'expired' => response()->view('password.public-share', ['status' => 'expired'], 410),
            'consumed' => response()->view('password.public-share', ['status' => 'consumed'], 410),
            'ready' => view('password.public-share', [
                'status' => 'reveal_prompt',
                'token' => $token,
            ]),
            default => response()->view('password.public-share', ['status' => 'not_found'], 404),
        };
    }

    public function revealPasswordShare(string $token)
    {
        $resolution = $this->resolvePasswordShareForPublic($token);

        return match ($resolution['status'])
        {
            'not_found' => response()->view('password.public-share', ['status' => 'not_found'], 404),
            'expired' => response()->view('password.public-share', ['status' => 'expired'], 410),
            'consumed' => response()->view('password.public-share', ['status' => 'consumed'], 410),
            'ready' => $this->consumePasswordShareAndRender($resolution['share']),
            default => response()->view('password.public-share', ['status' => 'not_found'], 404),
        };
    }

    /**
     * @return array{status: string, share?: TeamPasswordShare}
     */
    private function resolvePasswordShareForPublic(string $token): array
    {
        $tokenHash = hash('sha256', $token);

        /** @var TeamPasswordShare|null $share */
        $share = TeamPasswordShare::query()
            ->where('token_hash', $tokenHash)
            ->with('password')
            ->first();

        if (! $share || ! $share->password)
        {
            return ['status' => 'not_found'];
        }

        if ($share->isExpired())
        {
            return ['status' => 'expired'];
        }

        if ($share->isConsumed())
        {
            return ['status' => 'consumed'];
        }

        return ['status' => 'ready', 'share' => $share];
    }

    private function consumePasswordShareAndRender(TeamPasswordShare $share): \Illuminate\Contracts\View\View|\Illuminate\Http\Response
    {
        $password = $share->password;

        $revealed = DB::transaction(function () use ($share): bool
        {
            $locked = TeamPasswordShare::query()
                ->whereKey($share->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->isConsumed() || $locked->isExpired())
            {
                return false;
            }

            $locked->views_count = $locked->views_count + 1;
            if ($locked->views_count >= $locked->max_views)
            {
                $locked->consumed_at = now();
            }

            $locked->save();

            return true;
        });

        if (! $revealed)
        {
            $share->refresh();

            if ($share->isExpired())
            {
                return response()->view('password.public-share', ['status' => 'expired'], 410);
            }

            return response()->view('password.public-share', ['status' => 'consumed'], 410);
        }

        return view('password.public-share', [
            'status' => 'ok',
            'name' => $password->name,
            'username' => $password->username,
            'password' => $password->getPasswordPlainText(),
            'url' => $password->url,
            'notes' => $password->notes,
        ]);
    }

    private function assertPasswordsModuleEnabled(): void
    {
        $user = auth()->user();
        abort_unless($user?->currentTeam !== null, 403);
    }

    private function isVaultUnlocked(): bool
    {
        $team = auth()->user()?->currentTeam;
        if (! $team)
        {
            return false;
        }

        $isUnlocked = (bool) session("passwords_unlocked_team_{$team->id}", false);
        $unlockedUntil = (int) session("passwords_unlocked_until_team_{$team->id}", 0);

        return $isUnlocked && $unlockedUntil >= now()->timestamp;
    }
}
