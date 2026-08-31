<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Support\NewUserWelcomeEmailNotifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InboxContactAccessService
{
    public function __construct(
        private AssistantToolAuthorizationService $authorization,
    ) {}

    /**
     * @return array{id: int, name: string, email: string, staff: bool}|null
     */
    public function presentForContact(?Team $team, ?Contact $contact): ?array
    {
        if ($contact === null || ! filled($contact->user_id))
        {
            return null;
        }

        $user = User::query()->find($contact->user_id);
        if ($user === null)
        {
            return null;
        }

        return $this->presentUser($user, $team);
    }

    /**
     * Create or link a login, optionally set the password and email the access link.
     *
     * @return array{user: array{id: int, name: string, email: string, staff: bool}, created: bool, sent: bool}
     */
    public function apply(Team $team, Contact $contact, ?string $password, bool $sendAccess): array
    {
        $hadUser = filled($contact->user_id);
        if (! $hadUser)
        {
            $email = $this->usableEmail($contact->email);
            $existing = $email !== null ? User::query()->where('email', $email)->first() : null;
            if ($existing !== null && $this->isStaff($existing, $team))
            {
                throw new HttpException(422, 'Este email pertenece a un miembro del equipo. La clave no se cambia desde acá.');
            }
        }

        $user = $this->ensureUser($team, $contact->fresh());
        if ($user === null)
        {
            throw new HttpException(422, 'Para crear o enviar el acceso el contacto necesita un email.');
        }

        if ($this->isStaff($user, $team))
        {
            throw new HttpException(422, 'Este contacto está vinculado a un miembro del equipo. La clave no se cambia desde acá.');
        }

        if ($password !== null && $password !== '')
        {
            $user->forceFill(['password' => Hash::make($password)])->save();
        }

        if ($sendAccess)
        {
            NewUserWelcomeEmailNotifier::queue($user, $team);
        }

        return [
            'user' => $this->presentUser($user->fresh(), $team),
            'created' => ! $hadUser,
            'sent' => $sendAccess,
        ];
    }

    public function ensureUser(Team $team, Contact $contact): ?User
    {
        if (filled($contact->user_id))
        {
            return User::query()->find($contact->user_id);
        }

        $email = $this->usableEmail($contact->email);
        if ($email === null)
        {
            return null;
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null)
        {
            $contact->update(['user_id' => $existing->id]);
            if (! $existing->teams->contains($team->id))
            {
                $existing->teams()->attach($team->id);
            }

            return $existing;
        }

        $user = User::query()->create([
            'name' => trim((string) $contact->name) !== '' ? trim((string) $contact->name) : 'Cliente',
            'email' => $email,
            'phone' => preg_replace('/[^0-9]/', '', (string) ($contact->phone ?? '')) ?: null,
            'password' => Hash::make(Str::random(24)),
        ]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole('client');
        $user->teams()->attach($team->id);
        $contact->update(['user_id' => $user->id]);

        return $user;
    }

    /**
     * @return array{id: int, name: string, email: string, staff: bool}
     */
    private function presentUser(User $user, ?Team $team): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'staff' => $team !== null && $this->isStaff($user, $team),
        ];
    }

    private function isStaff(User $user, Team $team): bool
    {
        if ((int) $team->user_id === (int) $user->id || $user->hasRole('root'))
        {
            return true;
        }

        if ($user->hasRole('client'))
        {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'collaborator', 'developer', 'technical']))
        {
            return $this->authorization->hasFullAssistantToolAccess($user, (int) $team->id);
        }

        $membership = $user->teams()->where('teams.id', $team->id)->first();
        $pivotRole = $membership?->pivot?->role;

        return is_string($pivotRole) && in_array($pivotRole, ['admin', 'editor', 'collaborator'], true);
    }

    private function usableEmail(?string $email): ?string
    {
        $trimmed = strtolower(trim((string) $email));
        if ($trimmed === '' || NewUserWelcomeEmailNotifier::isPlaceholderInboxEmail($trimmed))
        {
            return null;
        }

        return $trimmed;
    }
}
