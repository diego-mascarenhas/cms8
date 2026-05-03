<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Demo WhatsApp threads for the team "Demo" (sidebar + {@see \App\Http\Controllers\ChatController::getChatList} / API list).
 * Uses a fictitious business line {@see self::DEMO_TEAM_WHATSAPP_LINE} so local seed does not depend on Twilio.
 *
 * Run after the Demo team exists: {@see TeamDemoSeeder} calls this automatically.
 * Standalone: php artisan db:seed --class=DemoWhatsAppConversationsSeeder
 */
class DemoWhatsAppConversationsSeeder extends Seeder
{
    /**
     * Fictitious WhatsApp assistant number for demo data (not a real Twilio line).
     */
    public const DEMO_TEAM_WHATSAPP_LINE = '34999000999';

    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if ($team === null)
        {
            $this->command?->warn('DemoWhatsAppConversationsSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('💬 Seeding demo WhatsApp conversations for team Demo...');

        $team->setSetting('whatsapp_from', self::DEMO_TEAM_WHATSAPP_LINE, [
            'type' => 'string',
            'group' => 'whatsapp',
            'is_encrypted' => false,
        ]);

        $line = self::DEMO_TEAM_WHATSAPP_LINE;
        $now = now();

        $threads = [
            [
                'peer' => '34600222001',
                'last_body' => 'Hola, querría información sobre facturación.',
                'hours_ago' => 1,
                'last_status' => 'received',
            ],
            [
                'peer' => '34600222002',
                'last_body' => 'Perfecto, gracias por la demo.',
                'hours_ago' => 5,
                'last_status' => 'read',
            ],
            [
                'peer' => '34600222003',
                'last_body' => '¿Podemos agendar una llamada mañana?',
                'hours_ago' => 12,
                'last_status' => 'received',
            ],
            [
                'peer' => '34600111002',
                'last_body' => 'Mensaje de prueba desde el contacto staff Demo User.',
                'hours_ago' => 20,
                'last_status' => 'read',
            ],
            [
                'peer' => '34600222004',
                'last_body' => 'Os envío el presupuesto en cuanto lo tenga.',
                'hours_ago' => 30,
                'last_status' => 'received',
            ],
        ];

        foreach ($threads as $index => $thread)
        {
            $peer = $thread['peer'];
            $lastAt = $now->copy()->subHours($thread['hours_ago']);

            Conversation::query()->updateOrCreate(
                ['message_sid' => 'SM_DEMO_SEED_THREAD_'.$index.'_FIRST'],
                [
                    'channel' => 'whatsapp',
                    'from' => $peer,
                    'to' => $line,
                    'body' => 'Hola, escribo desde el móvil (demo seed).',
                    'status' => 'read',
                    'direction' => 'inbound',
                    'created_at' => $lastAt->copy()->subDay(),
                    'updated_at' => $lastAt->copy()->subDay(),
                ],
            );

            Conversation::query()->updateOrCreate(
                ['message_sid' => 'SM_DEMO_SEED_THREAD_'.$index.'_OUT'],
                [
                    'channel' => 'whatsapp',
                    'from' => $line,
                    'to' => $peer,
                    'body' => 'Respuesta demo del equipo.',
                    'status' => 'delivered',
                    'direction' => 'outbound',
                    'created_at' => $lastAt->copy()->subHours(2),
                    'updated_at' => $lastAt->copy()->subHours(2),
                ],
            );

            Conversation::query()->updateOrCreate(
                ['message_sid' => 'SM_DEMO_SEED_THREAD_'.$index.'_LAST'],
                [
                    'channel' => 'whatsapp',
                    'from' => $peer,
                    'to' => $line,
                    'body' => $thread['last_body'],
                    'status' => $thread['last_status'],
                    'direction' => 'inbound',
                    'created_at' => $lastAt,
                    'updated_at' => $lastAt,
                ],
            );
        }

        Conversation::query()->updateOrCreate(
            ['message_sid' => 'SM_DEMO_SEED_EXTRA_UNREAD'],
            [
                'channel' => 'whatsapp',
                'from' => '34600222001',
                'to' => $line,
                'body' => 'Segundo mensaje sin leer (demo).',
                'status' => 'received',
                'direction' => 'inbound',
                'created_at' => $now->copy()->subMinutes(20),
                'updated_at' => $now->copy()->subMinutes(20),
            ],
        );

        $this->command?->info('✅ Demo WhatsApp: '.(count($threads) * 3 + 1).' mensajes en conversaciones (línea '.$line.').');
    }
}
