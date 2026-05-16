<?php

namespace Database\Seeders;

use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Team;
use App\Services\UserDailyPerformanceInsightNotificationService;
use App\Services\UserDailyPerformanceInsightService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

/**
 * Demo daily performance insight (same pipeline as the scheduled email) plus in-app notification.
 * Seeds mailbox emails so the digest highlights email volume alongside WhatsApp ({@see DemoWhatsAppConversationsSeeder}).
 *
 * Run after Demo team users and notifications exist: {@see TeamDemoSeeder} calls this after calendar seed.
 * Standalone: php artisan db:seed --class=DemoPerformanceInsightsSeeder
 */
class DemoPerformanceInsightsSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if ($team === null)
        {
            $this->command?->warn('DemoPerformanceInsightsSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('📊 Seeding demo daily performance insights for team Demo...');

        foreach (['performance_insights', 'mailbox'] as $moduleKey)
        {
            $team->enableModule($moduleKey);
        }

        $team->refresh();

        $mailbox = $this->ensureDemoMailbox($team);
        $emailCount = $this->seedDemoMailboxEmails($team, $mailbox);

        config([
            'daily_performance_insight.use_llm' => false,
            'daily_performance_insight.send_email' => false,
        ]);

        Mail::fake();

        $this->call(NotificationTypesSeeder::class);

        $insightService = app(UserDailyPerformanceInsightService::class);
        $notificationService = app(UserDailyPerformanceInsightNotificationService::class);
        $insightDate = now();
        $insightCount = 0;
        $notificationCount = 0;

        foreach ($team->allUsers()->unique('id') as $user)
        {
            if (! $user->hasAnyRole(['admin', 'root']))
            {
                continue;
            }

            $insight = $insightService->ensureTodayRecord($user, $team, null, $insightDate, true, 'es');
            $insightCount++;

            $notification = $notificationService->syncForInsight($insight, $team, markUnread: true);
            if ($notification !== null)
            {
                $notificationCount++;
            }
        }

        $this->command?->info(sprintf(
            '✅ Demo performance insights: %d emails, %d insight(s), %d in-app notification(s).',
            $emailCount,
            $insightCount,
            $notificationCount,
        ));
    }

    private function ensureDemoMailbox(Team $team): Mailbox
    {
        return Mailbox::query()->firstOrCreate(
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
    }

    /**
     * @return int Number of email rows upserted
     */
    private function seedDemoMailboxEmails(Team $team, Mailbox $mailbox): int
    {
        $now = now();
        $count = 0;

        foreach ($this->demoEmailFixtures() as $index => $fixture)
        {
            $messageDate = $now->copy()->subHours($fixture['hours_ago']);

            Email::query()->updateOrCreate(
                [
                    'mailbox_id' => $mailbox->id,
                    'message_id' => 'demo-performance-insight-'.$index,
                ],
                [
                    'team_id' => $team->id,
                    'subject' => $fixture['subject'],
                    'body_text' => $fixture['body'],
                    'from_address' => $fixture['from'],
                    'to_address' => 'demo-inbox@humano.app',
                    'message_date' => $messageDate,
                    'seen' => $fixture['seen'],
                    'flagged' => $fixture['flagged'] ?? false,
                ],
            );

            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{subject: string, from: string, body: string, hours_ago: int, seen: bool, flagged?: bool}>
     */
    private function demoEmailFixtures(): array
    {
        return [
            [
                'subject' => 'Re: Factura pendiente — IDONEO',
                'from' => 'contabilidad@idoneo.es',
                'body' => 'Buenos días, adjuntamos el detalle de la factura pendiente de revisión.',
                'hours_ago' => 1,
                'seen' => false,
                'flagged' => true,
            ],
            [
                'subject' => 'Presupuesto Q2 logística',
                'from' => 'javier.castro@cliente15.com',
                'body' => '¿Podéis confirmar plazos de entrega para el presupuesto enviado la semana pasada?',
                'hours_ago' => 3,
                'seen' => false,
            ],
            [
                'subject' => 'Confirmación reunión demo',
                'from' => 'sarah.johnson@humano.app',
                'body' => 'Quedo a la espera de la confirmación de la reunión de esta tarde.',
                'hours_ago' => 5,
                'seen' => true,
            ],
            [
                'subject' => 'Consulta soporte técnico #4821',
                'from' => 'soporte@cliente-demo.es',
                'body' => 'El cliente reporta un error al acceder al panel de facturación.',
                'hours_ago' => 8,
                'seen' => false,
            ],
            [
                'subject' => 'URGENTE: Pago domiciliación fallido',
                'from' => 'avisos@banco-demo.es',
                'body' => 'No hemos podido cargar el recibo. Por favor, revisad el método de pago.',
                'hours_ago' => 12,
                'seen' => false,
                'flagged' => true,
            ],
            [
                'subject' => 'RE: Contrato mantenimiento 2026',
                'from' => 'david.rodriguez@humano.app',
                'body' => 'Os envío la versión firmada del contrato de mantenimiento anual.',
                'hours_ago' => 18,
                'seen' => false,
            ],
            [
                'subject' => 'Recordatorio cita calendario',
                'from' => 'noreply@humano.app',
                'body' => 'Tienes una cita programada mañana a las 10:00 con el equipo comercial.',
                'hours_ago' => 2,
                'seen' => false,
            ],
            [
                'subject' => 'Entrega documentación proyecto',
                'from' => 'emma.wilson@humano.app',
                'body' => 'Documentación del hito 2 disponible en el enlace compartido.',
                'hours_ago' => 30,
                'seen' => true,
            ],
            [
                'subject' => 'Newsletter — novedades Humano',
                'from' => 'news@humano.app',
                'body' => 'Resumen mensual de funciones y mejoras de la plataforma.',
                'hours_ago' => 52,
                'seen' => true,
            ],
            [
                'subject' => 'Seguimiento propuesta comercial',
                'from' => 'michael.chen@humano.app',
                'body' => '¿Hay novedades sobre la propuesta enviada el lunes?',
                'hours_ago' => 6,
                'seen' => false,
            ],
        ];
    }
}
