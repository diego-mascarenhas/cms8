<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Actions\Fortify\ResetUserPassword;
use App\Helpers\TokenHelper;
use App\Http\Requests\UpdateUserProfilePhotoRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\TeamModulesByPricingPlanSyncer;
use App\Support\AuthIntendedUrlGuard;
use App\Support\ChatMessageAvatar;
use App\Support\EnsureRegisteredUserRole;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use PasswordValidationRules;

    public function register(Request $request)
    {
        $rules = [
            'name' => 'required|string',
            'email' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            'ref' => 'nullable|string|max:64',
        ];

        $messages = [
            'name.required' => 'El campo nombre es obligatorio',
            'name.string' => 'El campo nombre debe ser una cadena de texto',
            'email.required' => 'El campo email es obligatorio',
            'email.string' => 'El campo email debe ser una cadena de texto',
            'email.unique' => 'El email ya está registrado. Por favor, use otro',
            'email.email' => 'El correo electrónico no es válido',
            'password.required' => 'El campo contraseña es obligatorio',
            'password.string' => 'El campo contraseña debe ser una cadena de texto',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails())
        {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $this->createPersonalTeamForApiUser($user);
        $this->applyAffiliateReferralFromRequest($request, $user->fresh());
        event(new Registered($user->fresh()));

        $user->load(['currentTeam', 'roles']);
        $token = $user->createToken('IDONEO Access Token')->plainTextToken;
        $profile = $this->profilePayload($user);

        return response()->json([
            'email' => $user->email,
            'token' => $token,
            'user' => $profile,
            'current_team' => $profile['current_team'],
        ], 200);
    }

    public function login(Request $request)
    {
        $rules = [
            'email' => 'required',
            'password' => 'required|string',
            'remember_me' => 'boolean',
            'team_id' => 'nullable|integer|exists:teams,id',
        ];

        $request->validate($rules);

        $user = User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password))
        {
            if ($request->filled('team_id'))
            {
                $belongsToTeam = $user->teams()->where('teams.id', $request->team_id)->exists();
                if ($belongsToTeam)
                {
                    $user->forceFill(['current_team_id' => $request->team_id])->save();
                }
            }

            EnsureRegisteredUserRole::assignIfMissing($user);
            $user->load(['currentTeam', 'roles']);
            $token = $user->createToken('IDONEO Access Token')->plainTextToken;
            $profile = $this->profilePayload($user);

            $response = [
                'email' => $user->email,
                'token' => $token,
                'user' => $profile,
                'current_team' => $profile['current_team'],
            ];

            return response()->json($response, 200);
        }

        $response = ['message' => 'Los datos de acceso son incorrectos'];

        return response()->json($response, 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Su sesión se ha cerrado correctamente',
        ]);
    }

    /**
     * Return authenticated user with current team (for API clients e.g. IDONEO app).
     */
    public function user(Request $request)
    {
        return response()->json($this->profilePayload($request->user()));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => __('Perfil actualizado correctamente.'),
            'user' => $this->profilePayload($user->fresh(['currentTeam', 'roles'])),
        ]);
    }

    public function showProfilePhoto(Request $request)
    {
        $user = $request->user();
        $path = $user?->profile_photo_path;
        if (! is_string($path) || $path === '')
        {
            return response()->noContent();
        }

        $disk = Storage::disk((string) config('jetstream.profile_photo_disk', 'public'));
        if (! $disk->exists($path))
        {
            return response()->noContent();
        }

        return $disk->response($path);
    }

    public function updateProfilePhoto(UpdateUserProfilePhotoRequest $request)
    {
        $user = $request->user();
        $user->updateProfilePhoto($request->file('photo'));

        return response()->json([
            'success' => true,
            'message' => __('Foto de perfil actualizada.'),
            'user' => $this->profilePayload($user->fresh(['currentTeam', 'roles'])),
        ]);
    }

    public function deleteProfilePhoto(Request $request)
    {
        $user = $request->user();
        if (! $user)
        {
            return response()->json(['success' => false], 401);
        }

        $user->deleteProfilePhoto();

        return response()->json([
            'success' => true,
            'message' => __('Foto de perfil eliminada.'),
            'user' => $this->profilePayload($user->fresh(['currentTeam', 'roles'])),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['nullable', 'string'],
            'password' => $this->passwordRules(),
        ]);

        if (! empty($validated['current_password']) && ! Hash::check($validated['current_password'], $user->password))
        {
            throw ValidationException::withMessages([
                'current_password' => [__('La contraseña actual no es correcta.')],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => __('Contraseña actualizada correctamente.'),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'frontend_url' => ['nullable', 'string', 'max:255'],
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => __('Te enviamos un enlace para restablecer la contraseña si el email existe.'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void
            {
                app(ResetUserPassword::class)->reset($user, $request->only('password', 'password_confirmation'));
            },
        );

        if ($status !== Password::PASSWORD_RESET)
        {
            return response()->json([
                'message' => __('No se pudo restablecer la contraseña. El enlace puede haber caducado.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Contraseña actualizada. Ya puedes entrar.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        $user->loadMissing(['currentTeam', 'roles']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone !== null ? (string) $user->phone : null,
            'role' => $this->formatUserRoleLabel($user),
            'profile_photo_url' => ChatMessageAvatar::forUser($user)['photo_url'] ?? null,
            'current_team' => $user->currentTeam ? [
                'id' => $user->currentTeam->id,
                'name' => $user->currentTeam->name,
                'is_owner' => $user->ownsTeam($user->currentTeam),
                'can_manage' => $user->canManageTeam($user->currentTeam),
                'apps' => app(AssistantSubscriptionService::class)->appsPayload($user->currentTeam),
            ] : null,
        ];
    }

    private function formatUserRoleLabel(User $user): string
    {
        return $user->roles
            ->pluck('name')
            ->map(fn (string $name) => ucfirst($name))
            ->implode(', ');
    }

    /**
     * Login user with token for auto-access to client area
     */
    public function loginWithToken($token)
    {
        try
        {
            // First check if token was revoked before trying to validate
            $payload = TokenHelper::getTokenPayload($token);
            if ($payload)
            {
                $userId = $payload['user_id'] ?? null;
                $purpose = $payload['purpose'] ?? 'autologin';
                if ($userId)
                {
                    $revocationKey = "user_token_revocation_{$userId}_{$purpose}";
                    $revocationTimestamp = \Illuminate\Support\Facades\Cache::get($revocationKey);
                    if ($revocationTimestamp && isset($payload['iat']) && $payload['iat'] < $revocationTimestamp)
                    {
                        // Token was revoked - show 403
                        abort(403, 'Este link de acceso ha sido revocado y ya no es válido.');
                    }

                    // Also check if individual token was revoked by jti
                    if (isset($payload['jti']))
                    {
                        $revokedKey = 'revoked_token_'.$payload['jti'];
                        if (\Illuminate\Support\Facades\Cache::has($revokedKey))
                        {
                            abort(403, 'Este link de acceso ha sido revocado y ya no es válido.');
                        }
                    }
                }
            }

            // First, try to parse as signed token (new format)
            $user = TokenHelper::validateSignedToken($token);

            // If signed token validation fails, try Sanctum token (legacy)
            if (! $user)
            {
                $user = $this->validateSanctumToken($token);
            }

            if (! $user)
            {
                return redirect()->route('login')->withErrors(['error' => 'Token inválido o expirado']);
            }

            // Log the user in using the session guard
            auth()->login($user, true);

            // Ensure a current team is bound before the dashboard request.
            // Users with null current_team_id otherwise hit 500 until a later request.
            if (! $user->currentTeam)
            {
                $team = $user->allTeams()->first();
                if ($team)
                {
                    $user->switchTeam($team);
                }
            }

            if (! $user->currentTeam)
            {
                return redirect()->route('error-without-team');
            }

            // Honor url.intended when present; otherwise analytics (no role-based redirect).
            $default = route('dashboard');
            $intended = session()->pull('url.intended', $default);
            $target = AuthIntendedUrlGuard::sanitizeIntendedUrl($intended, $default);

            return redirect()->to($target);
        } catch (\Exception $e)
        {
            \Log::error('Error in token login: '.$e->getMessage());

            return redirect()->route('login')->withErrors(['error' => 'Error al procesar el token']);
        }
    }

    /**
     * Validate Sanctum token (legacy support)
     */
    private function validateSanctumToken($token)
    {
        try
        {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

            if (! $accessToken || $accessToken->expires_at < now())
            {
                return null;
            }

            $user = $accessToken->tokenable;

            if ($user)
            {
                // Revoke the token since it was used for single login
                $accessToken->delete();
                \Log::info('Sanctum token validated and revoked', ['user_id' => $user->id]);
            }

            return $user;
        } catch (\Exception $e)
        {
            \Log::error('Error validating Sanctum token: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Auto-login as the admin (owner) of the demo team. Public route for demo access.
     */
    public function demoLogin(): RedirectResponse
    {
        $team = Team::whereRaw('LOWER(name) LIKE ?', ['%demo%'])->first();

        if (! $team)
        {
            return redirect()->route('login')->withErrors(['error' => __('Demo team not found.')]);
        }

        $user = User::withoutGlobalScopes()->find($team->user_id);

        if (! $user)
        {
            return redirect()->route('login')->withErrors(['error' => __('Demo team has no owner.')]);
        }

        auth()->login($user, true);
        $user->forceFill(['current_team_id' => $team->id])->save();
        request()->session()->regenerate();

        if ($user->hasRole('admin'))
        {
            return redirect()->route('dashboard');
        }
        if ($user->hasRole('collaborator'))
        {
            return redirect()->route('dashboard.collaborator');
        }
        if ($user->hasRole('client'))
        {
            return redirect()->route('dashboard.client');
        }

        return redirect()->route('dashboard');
    }

    private function createPersonalTeamForApiUser(User $user): void
    {
        $team = $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]));

        $user->forceFill([
            'current_team_id' => $team->id,
        ])->save();

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan(
            $team,
            (string) config('humano_pricing.registration_team_plan_slug', 'hunter'),
        );
    }

    private function applyAffiliateReferralFromRequest(Request $request, ?User $user): void
    {
        if ($user === null)
        {
            return;
        }

        $ref = trim((string) $request->input('ref', ''));
        if ($ref === '' || ! str_starts_with(strtolower($ref), 'cus_'))
        {
            return;
        }

        $referrer = Team::findByStripeCustomerId($ref);
        $team = $user->currentTeam;
        if ($referrer === null || $team === null || (int) $team->id === (int) $referrer->id)
        {
            return;
        }

        if (trim((string) ($team->referred_by ?? '')) !== '')
        {
            return;
        }

        $team->forceFill(['referred_by' => $ref])->save();
    }
}
