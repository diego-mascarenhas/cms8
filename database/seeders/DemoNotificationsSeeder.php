<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo notifications for team "Demo" (navbar bell + notification module).
 * Links contacts to users by matching email when both belong to the Demo team.
 *
 * Standalone: php artisan db:seed --class=DemoNotificationsSeeder
 * Included from {@see TeamDemoSeeder} and {@see DemoSeeder}.
 */
class DemoNotificationsSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::withoutGlobalScopes()->where('name', 'Demo')->orderBy('id')->first();

        if (! $team)
        {
            $this->command?->warn('DemoNotificationsSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('🔔 Seeding demo notifications for team Demo...');

        $this->call(NotificationTypesSeeder::class);

        $types = NotificationType::query()->pluck('id', 'name');
        if ($types->isEmpty())
        {
            $this->command?->warn('DemoNotificationsSeeder: no notification types — skip.');

            return;
        }

        $sender = User::query()->where('email', 'admin@humano.app')->first()
            ?? User::query()->find($team->user_id);

        if (! $sender)
        {
            $this->command?->warn('DemoNotificationsSeeder: no sender user — skip.');

            return;
        }

        $linked = $this->linkContactsToTeamUsers($team);
        $this->command?->info("   Linked {$linked} contact(s) to users by email.");

        $created = $this->seedNotifications($team, $sender, $types);

        $this->command?->info("✅ Demo notifications: {$created} notification(s) seeded.");
    }

    private function linkContactsToTeamUsers(Team $team): int
    {
        $linked = 0;

        $teamUserIds = $team->users()->pluck('users.id');

        Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNotNull('email')
            ->each(function (Contact $contact) use ($teamUserIds, &$linked): void
            {
                $user = User::query()
                    ->where('email', $contact->email)
                    ->whereIn('id', $teamUserIds)
                    ->first();

                if (! $user)
                {
                    return;
                }

                if ((int) $contact->user_id !== (int) $user->id)
                {
                    $contact->forceFill(['user_id' => $user->id])->save();
                    $linked++;
                }
            });

        return $linked;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $types
     */
    private function seedNotifications(Team $team, User $sender, $types): int
    {
        $fixtures = [
            [
                'recipient_email' => 'admin@humano.app',
                'type' => 'Welcome Message',
                'subject' => 'Bienvenido al equipo Demo',
                'message' => 'Tu perfil de contacto está vinculado. Aquí verás avisos de proyectos, pagos y mensajes del equipo.',
                'is_sent' => true,
                'is_read' => false,
                'days_ago' => 1,
            ],
            [
                'recipient_email' => 'admin@humano.app',
                'type' => 'Project Assignment',
                'subject' => 'Nuevo proyecto: Web corporativa Demo',
                'message' => 'Se te ha asignado el seguimiento del proyecto de web corporativa. Revisa el briefing en el panel de proyectos.',
                'is_sent' => true,
                'is_read' => false,
                'days_ago' => 2,
            ],
            [
                'recipient_email' => 'admin@humano.app',
                'type' => 'General Message',
                'subject' => 'Recordatorio de actualización de perfil',
                'message' => 'Por favor confirma tus datos de contacto y idiomas de trabajo en tu ficha.',
                'is_sent' => true,
                'is_read' => true,
                'days_ago' => 5,
            ],
            [
                'recipient_email' => 'sarah.johnson@humano.app',
                'type' => 'Project Assignment',
                'subject' => 'Proyecto asignado: Campaña Q2',
                'message' => 'Coordina con el cliente la entrega de la campaña del segundo trimestre.',
                'is_sent' => true,
                'is_read' => false,
                'days_ago' => 1,
            ],
            [
                'recipient_email' => 'john.smith@humano.app',
                'type' => 'Task Assignment',
                'subject' => 'Tarea: Revisión API móvil',
                'message' => 'Revisa los endpoints del chat móvil antes del despliegue de la demo.',
                'is_sent' => true,
                'is_read' => false,
                'days_ago' => 3,
            ],
            [
                'recipient_email' => 'jennifer.lee@humano.app',
                'type' => 'General Message',
                'subject' => 'Feedback de calidad recibido',
                'message' => 'El cliente ha valorado positivamente la última entrega. ¡Buen trabajo!',
                'is_sent' => true,
                'is_read' => true,
                'days_ago' => 4,
            ],
            [
                'recipient_email' => 'michael.chen@humano.app',
                'type' => 'Payment Reminder',
                'subject' => 'Recordatorio de factura pendiente',
                'message' => 'Tienes una factura de demo pendiente de revisión en el módulo de facturación.',
                'is_sent' => true,
                'is_read' => false,
                'days_ago' => 6,
            ],
            [
                'recipient_email' => 'admin@humano.app',
                'type' => 'Project Update',
                'subject' => 'Actualización: plazos del proyecto IDONEO',
                'message' => 'Se ha ampliado la fecha de entrega una semana. Consulta el detalle en proyectos.',
                'is_sent' => false,
                'is_read' => false,
                'days_ago' => 0,
            ],
        ];

        $created = 0;

        foreach ($fixtures as $fixture)
        {
            $contact = $this->resolveRecipientContact($team, $fixture['recipient_email'], $sender);

            if (! $contact)
            {
                continue;
            }

            $typeId = $types[$fixture['type']] ?? $types->first();

            $createdAt = Carbon::now()->subDays((int) ($fixture['days_ago'] ?? 0));
            $isSent = (bool) ($fixture['is_sent'] ?? true);
            $isRead = (bool) ($fixture['is_read'] ?? false);

            Notification::withoutGlobalScopes()->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'contact_id' => $contact->id,
                    'subject' => $fixture['subject'],
                ],
                [
                    'type_id' => $typeId,
                    'user_id' => $sender->id,
                    'message' => $fixture['message'],
                    'is_sent' => $isSent,
                    'sent_at' => $isSent ? $createdAt->copy()->addHours(2) : null,
                    'sent_data' => $isSent ? ['channel' => 'email', 'demo_seed' => true] : null,
                    'is_read' => $isRead,
                    'read_at' => $isRead ? $createdAt->copy()->addDay() : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ],
            );

            $created++;
        }

        return $created;
    }

    private function resolveRecipientContact(Team $team, string $email, User $fallbackOwner): ?Contact
    {
        $user = User::query()
            ->where('email', $email)
            ->whereHas('teams', function (Builder $query) use ($team): void
            {
                $query->where('teams.id', $team->id);
            })
            ->first();

        if (! $user)
        {
            return null;
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', $email)
            ->first();

        if (! $contact)
        {
            [$name, $surname] = $this->splitUserName($user->name);

            $contact = Contact::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'email' => $email,
                'name' => $name,
                'surname' => $surname,
                'phone' => (string) ($user->phone ?? '34600000000'),
                'creator_id' => $fallbackOwner->id,
                'responsible_id' => $fallbackOwner->id,
                'status_id' => 1,
                'country' => 724,
                'language' => 'es',
                'engagment' => 'temperate',
                'user_id' => $user->id,
            ]);
        } elseif ((int) $contact->user_id !== (int) $user->id)
        {
            $contact->forceFill(['user_id' => $user->id])->save();
        }

        return $contact;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitUserName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [
            $parts[0] ?? $fullName,
            $parts[1] ?? '',
        ];
    }
}
