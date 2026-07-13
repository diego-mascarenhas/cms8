<?php

namespace Database\Seeders;

use App\Enums\ContactInteractionType;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\ContactStatus;
use App\Models\Team;
use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * QA fixtures for team roles: portal client vs collaborator (billing / infra visibility).
 *
 * Standalone: php artisan db:seed --class=DemoTeamRoleScenariosSeeder
 * Included from {@see DemoSeeder} and {@see TeamDemoSeeder}.
 */
class DemoTeamRoleScenariosSeeder extends Seeder
{
    public const QA_CLIENT_EMAIL = 'client@humano.app';

    public const QA_COLLABORATOR_EMAIL = 'collaborator@humano.app';

    public const QA_PASSWORD = 'Simplicity!';

    public const PORTAL_CONTACT_EMAIL = 'carlos.garcia@cliente1.com';

    public const COLLABORATOR_DASHBOARD_CONTACTS = 30;

    public function run(): void
    {
        $team = Team::withoutGlobalScopes()->where('name', 'Demo')->orderBy('id')->first();

        if (! $team)
        {
            $this->command?->warn('DemoTeamRoleScenariosSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('🧪 Seeding QA team role scenarios (client portal vs collaborator)...');

        foreach (['client', 'collaborator'] as $roleName)
        {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $admin = User::query()->where('email', 'admin@humano.app')->first()
            ?? $team->owner;

        if (! $admin)
        {
            $this->command?->warn('DemoTeamRoleScenariosSeeder: no admin user — skip.');

            return;
        }

        $synchronizer = app(JetstreamTeamRoleSynchronizer::class);

        $clientUser = $this->seedTeamMember(
            $team,
            self::QA_CLIENT_EMAIL,
            'Demo Client',
            'client',
            $synchronizer,
        );

        $this->linkPortalContact($team, $clientUser, $admin);

        $this->seedTeamMember(
            $team,
            self::QA_COLLABORATOR_EMAIL,
            'Demo Collaborator',
            'collaborator',
            $synchronizer,
        );

        $collaboratorUser = User::query()->where('email', self::QA_COLLABORATOR_EMAIL)->first();
        if ($collaboratorUser)
        {
            $assignedCount = $this->assignDemoContactsToCollaborator($team, $collaboratorUser, $clientUser->id);
            $this->command?->info("   → {$assignedCount} demo contacts assigned to ".self::QA_COLLABORATOR_EMAIL.' (dashboard + list)');
        }

        $this->printManualTestGuide($team);

        $this->command?->info('✅ Demo team role QA users ready.');
    }

    private function seedTeamMember(
        Team $team,
        string $email,
        string $name,
        string $jetstreamRole,
        JetstreamTeamRoleSynchronizer $synchronizer,
    ): User {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::QA_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        if ($user->teams()->where('team_id', $team->id)->exists())
        {
            $team->users()->updateExistingPivot($user->id, ['role' => $jetstreamRole]);
        } else
        {
            $user->teams()->attach($team->id, ['role' => $jetstreamRole]);
        }

        $synchronizer->sync($user->fresh(), $jetstreamRole);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    private function linkPortalContact(Team $team, User $clientUser, User $admin): void
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', self::PORTAL_CONTACT_EMAIL)
            ->first();

        if (! $contact)
        {
            $statusId = ContactStatus::query()->value('id') ?? 1;

            $contact = Contact::query()->create([
                'team_id' => $team->id,
                'name' => 'Carlos',
                'surname' => 'García López',
                'email' => self::PORTAL_CONTACT_EMAIL,
                'phone' => 34600222001,
                'language' => 'es',
                'country' => 724,
                'creator_id' => $admin->id,
                'responsible_id' => $admin->id,
                'status_id' => $statusId,
                'profile' => 'Contacto demo vinculado al portal cliente QA.',
            ]);
        }

        $contact->update(['user_id' => $clientUser->id]);
    }

    private function printManualTestGuide(Team $team): void
    {
        $teamUrl = url('/teams/'.$team->id);
        $loginUrl = url('/login');

        $this->command?->newLine();
        $this->command?->line('── Manual QA: roles de equipo ──');
        $this->command?->line('1. Admin: admin@humano.app / '.self::QA_PASSWORD);
        $this->command?->line('   Equipo: '.$teamUrl);
        $this->command?->line('2. Cliente portal: '.self::QA_CLIENT_EMAIL.' / '.self::QA_PASSWORD);
        $this->command?->line('   → No debe ver Billing ni Infraestructura.');
        $this->command?->line('3. Colaborador: '.self::QA_COLLABORATOR_EMAIL.' / '.self::QA_PASSWORD);
        $this->command?->line('   → Ve todos los contactos del equipo; no puede cambiar el asesor.');
        $this->command?->line('4. En el equipo, intentar añadir '.self::QA_CLIENT_EMAIL.' como Colaborador → error.');
        $this->command?->line('5. Quitar y volver a añadir como Cliente → rol Spatie coherente.');
        $this->command?->line('Login: '.$loginUrl);
        $this->command?->newLine();
    }

    private function assignDemoContactsToCollaborator(Team $team, User $collaborator, int $excludeUserId): int
    {
        $baseQuery = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where(function ($query) use ($excludeUserId)
            {
                $query->whereNull('user_id')
                    ->orWhere('user_id', '!=', $excludeUserId);
            });

        $contactIds = (clone $baseQuery)
            ->whereHas('currentSentiment')
            ->orderByDesc('id')
            ->limit(self::COLLABORATOR_DASHBOARD_CONTACTS)
            ->pluck('id');

        if ($contactIds->count() < 10)
        {
            $contactIds = (clone $baseQuery)
                ->orderByDesc('id')
                ->limit(self::COLLABORATOR_DASHBOARD_CONTACTS)
                ->pluck('id');
        }

        if ($contactIds->isEmpty())
        {
            return 0;
        }

        $updated = Contact::withoutGlobalScopes()
            ->whereIn('id', $contactIds)
            ->update(['responsible_id' => $collaborator->id]);

        $this->prepareCollaboratorDashboardMetrics($contactIds, $collaborator->id);

        return $updated;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|list<int>  $contactIds
     */
    private function prepareCollaboratorDashboardMetrics($contactIds, int $collaboratorId): void
    {
        $ids = collect($contactIds)->values()->all();

        if ($ids === [])
        {
            return;
        }

        foreach ([1, 2, 3, 4, 5] as $index => $statusId)
        {
            if (! isset($ids[$index]))
            {
                break;
            }

            Contact::withoutGlobalScopes()
                ->where('id', $ids[$index])
                ->update([
                    'status_id' => $statusId,
                    'created_at' => now()->subDays($index + 1),
                ]);
        }

        Contact::withoutGlobalScopes()
            ->whereIn('id', array_slice($ids, 0, min(8, count($ids))))
            ->update([
                'status_id' => 1,
                'created_at' => now()->subDays(2),
            ]);

        $this->seedCollaboratorDashboardInteractions($ids, $collaboratorId);
    }

    /**
     * @param  list<int>  $contactIds
     */
    private function seedCollaboratorDashboardInteractions(array $contactIds, int $collaboratorId): void
    {
        $recentContactIds = array_slice($contactIds, 0, min(12, count($contactIds)));

        foreach ($recentContactIds as $index => $contactId)
        {
            $hasRecentInteraction = ContactInteraction::query()
                ->where('contact_id', $contactId)
                ->where('occurred_at', '>=', now()->subDays(30))
                ->exists();

            if ($hasRecentInteraction)
            {
                continue;
            }

            ContactInteraction::query()->create([
                'contact_id' => $contactId,
                'user_id' => $collaboratorId,
                'type' => ContactInteractionType::Call,
                'subject' => 'Seguimiento demo colaborador',
                'body' => 'Actividad demo para el panel del colaborador.',
                'occurred_at' => now()->subDays($index % 14)->setTime(10, ($index * 7) % 60),
            ]);
        }
    }
}
