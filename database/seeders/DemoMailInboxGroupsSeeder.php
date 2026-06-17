<?php

namespace Database\Seeders;

use App\Enums\EmailFolder;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Seeds grouped inbox demo emails (several messages per sender) for /mail/list testing.
 *
 * Standalone: php artisan db:seed --class=DemoMailInboxGroupsSeeder
 */
class DemoMailInboxGroupsSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if ($team === null)
        {
            $this->command?->warn('DemoMailInboxGroupsSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('📬 Seeding grouped mailbox demo emails for team Demo...');

        $team->enableModule('mailbox');
        $team->refresh();

        $mailbox = Mailbox::query()->firstOrCreate(
            [
                'team_id' => $team->id,
                'username' => 'demo-inbox@humano.app',
            ],
            [
                'name' => 'Demo Inbox',
                'host' => 'imap.demo.humano.local',
                'port' => 993,
                'encryption' => 'ssl',
                'password' => 'demo-mailbox-seed',
                'protocol' => 'imap',
                'folder' => 'INBOX',
            ],
        );

        $count = 0;

        foreach ($this->groupedEmailFixtures() as $fixture)
        {
            $messageDate = now()->subHours($fixture['hours_ago']);

            Email::query()->updateOrCreate(
                [
                    'mailbox_id' => $mailbox->id,
                    'message_id' => 'demo-mail-group-'.$fixture['message_key'],
                ],
                [
                    'team_id' => $team->id,
                    'subject' => $fixture['subject'],
                    'body_text' => $fixture['body'],
                    'from_address' => $fixture['from'],
                    'to_address' => $fixture['to'] ?? 'demo-inbox@humano.app',
                    'message_date' => $messageDate,
                    'seen' => $fixture['seen'],
                    'flagged' => $fixture['flagged'] ?? false,
                    'folder' => $fixture['folder'] ?? EmailFolder::Inbox->value,
                ],
            );

            $count++;
        }

        $this->command?->info(sprintf('✅ Demo mail groups: %d messages from %d senders.', $count, 4));
    }

    /**
     * @return list<array{message_key: string, subject: string, from: string, body: string, hours_ago: int, seen: bool, folder?: string, flagged?: bool, to?: string}>
     */
    private function groupedEmailFixtures(): array
    {
        return [
            [
                'message_key' => 'idoneo-1',
                'subject' => 'Factura pendiente — IDONEO',
                'from' => 'IDONEO Contabilidad <contabilidad@idoneo.es>',
                'body' => 'Buenos días, adjuntamos la factura pendiente de revisión.',
                'hours_ago' => 1,
                'seen' => false,
                'flagged' => true,
            ],
            [
                'message_key' => 'idoneo-2',
                'subject' => 'Re: Factura pendiente — IDONEO',
                'from' => 'IDONEO Contabilidad <contabilidad@idoneo.es>',
                'body' => '¿Podéis confirmar fecha de pago para la factura enviada ayer?',
                'hours_ago' => 3,
                'seen' => false,
            ],
            [
                'message_key' => 'idoneo-3',
                'subject' => 'Recordatorio factura IDONEO',
                'from' => 'IDONEO Contabilidad <contabilidad@idoneo.es>',
                'body' => 'Seguimos a la espera de vuestra confirmación.',
                'hours_ago' => 8,
                'seen' => true,
            ],
            [
                'message_key' => 'castro-1',
                'subject' => 'Presupuesto Q2 logística',
                'from' => 'Javier Castro <javier.castro@cliente15.com>',
                'body' => '¿Podéis confirmar plazos de entrega para el presupuesto enviado la semana pasada?',
                'hours_ago' => 2,
                'seen' => false,
            ],
            [
                'message_key' => 'castro-2',
                'subject' => 'Re: Presupuesto Q2 logística',
                'from' => 'Javier Castro <javier.castro@cliente15.com>',
                'body' => 'Necesitaríamos una respuesta antes del viernes para cerrar el trimestre.',
                'hours_ago' => 6,
                'seen' => false,
            ],
            [
                'message_key' => 'castro-3',
                'subject' => 'Actualización plazos presupuesto',
                'from' => 'Javier Castro <javier.castro@cliente15.com>',
                'body' => 'Os envío una versión revisada del presupuesto con nuevos plazos.',
                'hours_ago' => 12,
                'seen' => true,
            ],
            [
                'message_key' => 'humano-1',
                'subject' => 'Confirmación reunión demo',
                'from' => 'Sarah Johnson <sarah.johnson@humano.app>',
                'body' => 'Quedo a la espera de la confirmación de la reunión de esta tarde.',
                'hours_ago' => 4,
                'seen' => true,
            ],
            [
                'message_key' => 'humano-2',
                'subject' => 'Re: Confirmación reunión demo',
                'from' => 'Sarah Johnson <sarah.johnson@humano.app>',
                'body' => '¿Os encaja mover la reunión a las 16:00?',
                'hours_ago' => 5,
                'seen' => false,
            ],
            [
                'message_key' => 'support-1',
                'subject' => 'Consulta soporte técnico #4821',
                'from' => 'Soporte Técnico <soporte@proveedor-demo.com>',
                'body' => 'Hemos registrado vuestra incidencia y la estamos revisando.',
                'hours_ago' => 7,
                'seen' => false,
            ],
            [
                'message_key' => 'newsletter-1',
                'subject' => 'Novedades de producto — marzo',
                'from' => 'Newsletter Demo <newsletter@saas-demo.com>',
                'body' => 'Resumen mensual de novedades y mejoras de la plataforma.',
                'hours_ago' => 24,
                'seen' => true,
            ],
        ];
    }
}
