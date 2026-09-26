<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ClientLoginCodeMail;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use App\Services\RevisionAlphaBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClientPortalController extends Controller
{
    public function requestCode(Request $request): JsonResponse
    {
        $email = $this->validatedEmail($request);
        $user = $this->findUser($email);

        if ($user === null)
        {
            return response()->json([
                'registered' => false,
                'message' => 'Este email no está registrado.',
            ], 404);
        }

        return $this->sent($user->email);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower($validated['email']);
        if ($this->findUser($email) !== null)
        {
            return response()->json([
                'registered' => true,
                'message' => 'Este email ya está registrado.',
            ], 422);
        }

        $user = User::query()->getModel()->getConnection()->transaction(function () use ($validated, $email)
        {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'phone' => $this->phoneNumber($validated['phone'] ?? null),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $company = trim((string) ($validated['company_name'] ?? ''));
            $team = $user->ownedTeams()->save(Team::forceCreate([
                'user_id' => $user->id,
                'name' => $company !== '' ? $company : $user->name,
                'personal_team' => true,
            ]));
            $user->forceFill(['current_team_id' => $team->id])->save();

            return $user;
        });

        return $this->sent($user->email);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = strtolower($validated['email']);
        $user = $this->findUser($email);
        $hashed = Cache::get($this->cacheKey($email));

        if ($user === null || ! is_string($hashed) || ! Hash::check($validated['code'], $hashed))
        {
            return response()->json([
                'message' => 'Código incorrecto o caducado.',
            ], 422);
        }

        Cache::forget($this->cacheKey($email));
        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada.',
        ]);
    }

    public function dashboard(Request $request, RevisionAlphaBilling $billing): JsonResponse
    {
        $account = $billing->forEmail((string) $request->user()->email);

        return response()->json([
            'invoices' => $account['invoices'],
            'services' => $account['services'],
            'payments' => $account['payments'],
            'usage' => [
                'emails' => 0,
                'whatsapp' => 0,
                'ai_tokens' => 0,
            ],
            'leads' => [],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json($this->profilePayload($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $user->fill([
            'name' => $validated['name'],
            'phone' => $this->phoneNumber($validated['phone'] ?? null),
        ]);
        $user->save();

        $team = $user->currentTeam;
        if ($team)
        {
            $company = trim((string) ($validated['company_name'] ?? ''));
            if ($company !== '')
            {
                $team->forceFill(['name' => $company])->save();
            }

            TeamSetting::query()->updateOrCreate(
                ['team_id' => $team->id, 'key' => 'client_tax_id'],
                [
                    'value' => trim((string) ($validated['tax_id'] ?? '')),
                    'type' => 'string',
                    'group' => 'client',
                ],
            );
        }

        return response()->json($this->profilePayload($user->fresh()));
    }

    public function invoices(Request $request, RevisionAlphaBilling $billing): JsonResponse
    {
        return response()->json([
            'invoices' => $billing->forEmail((string) $request->user()->email)['invoices'],
        ]);
    }

    public function tickets(): JsonResponse
    {
        return response()->json(['tickets' => []]);
    }

    private function validatedEmail(Request $request): string
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        return strtolower($validated['email']);
    }

    private function findUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function sent(string $email): JsonResponse
    {
        try
        {
            $this->sendCode($email);
        } catch (\Throwable $exception)
        {
            report($exception);

            return response()->json([
                'message' => 'No se pudo enviar el código. Inténtalo de nuevo en unos minutos.',
            ], 503);
        }

        return response()->json([
            'registered' => true,
            'message' => 'Te enviamos un código de inicio de sesión.',
        ]);
    }

    private function sendCode(string $email): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey(strtolower($email)), Hash::make($code), now()->addMinutes(10));

        $mailer = app()->environment('local') ? 'mailpit' : (string) config('mail.default');
        Mail::mailer($mailer)->to($email)->send(new ClientLoginCodeMail($email, $code));
    }

    private function cacheKey(string $email): string
    {
        return 'client-login-code:'.$email;
    }

    private function phoneNumber(?string $phone): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '')
        {
            return null;
        }

        return (int) $digits;
    }

    /**
     * @return array{name: string, email: string, phone: string, company_name: string, tax_id: string}
     */
    private function profilePayload(User $user): array
    {
        $team = $user->currentTeam;
        $taxId = '';
        if ($team)
        {
            $taxId = (string) TeamSetting::query()
                ->where('team_id', $team->id)
                ->where('key', 'client_tax_id')
                ->value('value');
        }

        return [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'phone' => $user->phone ? (string) $user->phone : '',
            'company_name' => (string) ($team->name ?? ''),
            'tax_id' => $taxId,
        ];
    }
}
