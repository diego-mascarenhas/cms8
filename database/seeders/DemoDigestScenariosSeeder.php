<?php

namespace Database\Seeders;

use App\Enums\EmailFolder;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\EnterpriseTaxStatusType;
use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Models\Mailbox;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic demo data for daily performance digest AI suggestions:
 * enterprises, contacts, paid/unpaid invoices, WhatsApp threads and mailbox emails.
 *
 * Run after {@see TeamDemoSeeder} client contacts exist, before {@see DemoPerformanceInsightsSeeder}.
 * Standalone: php artisan db:seed --class=DemoDigestScenariosSeeder
 */
class DemoDigestScenariosSeeder extends Seeder
{
    public const DEMO_WHATSAPP_LINE = DemoWhatsAppConversationsSeeder::DEMO_TEAM_WHATSAPP_LINE;

    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if ($team === null)
        {
            $this->command?->warn('DemoDigestScenariosSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('🧾 Seeding digest demo scenarios (invoices, chat, email)...');

        if (EnterpriseTaxStatusType::query()->doesntExist())
        {
            $this->call(EnterpriseTaxStatusTypeSeeder::class);
        }

        foreach (['invoices', 'chat', 'mailbox', 'calendar', 'performance_insights'] as $moduleKey)
        {
            $team->enableModule($moduleKey);
        }

        $team->refresh();
        $team->setSetting('whatsapp_from', self::DEMO_WHATSAPP_LINE, [
            'type' => 'string',
            'group' => 'whatsapp',
            'is_encrypted' => false,
        ]);

        $ownerId = (int) (User::whereHas('teams', fn ($q) => $q->where('team_id', $team->id))->value('id') ?? 1);

        $this->seedExtendedCatalog($team, $ownerId);

        foreach ($this->digestScenarios() as $scenario)
        {
            $this->applyScenario($team, $ownerId, $scenario);
        }

        $this->command?->info('✅ Digest demo scenarios seeded.');
    }

    /**
     * Extra enterprises and contacts for a realistic CRM volume.
     */
    private function seedExtendedCatalog(Team $team, int $ownerId): void
    {
        $extraEnterprises = [
            ['code' => 'DEMO021', 'name' => 'Nordic Retail Group', 'email' => 'info@nordic-retail.demo'],
            ['code' => 'DEMO022', 'name' => 'Atlántida Logística', 'email' => 'contacto@atlantida-log.demo'],
            ['code' => 'DEMO023', 'name' => 'Solaris Energía', 'email' => 'admin@solaris-energia.demo'],
            ['code' => 'DEMO024', 'name' => 'Pyme Digital Hub', 'email' => 'hola@pyme-digital.demo'],
            ['code' => 'DEMO025', 'name' => 'Grupo FarmaSalud', 'email' => 'facturacion@farmasalud.demo'],
            ['code' => 'DEMO026', 'name' => 'Estudio Creativo Lima', 'email' => 'studio@creativo-lima.demo'],
            ['code' => 'DEMO027', 'name' => 'Inmobiliaria Horizonte', 'email' => 'gestion@horizonte.demo'],
            ['code' => 'DEMO028', 'name' => 'FoodTech Delivery', 'email' => 'ops@foodtech.demo'],
        ];

        foreach ($extraEnterprises as $index => $row)
        {
            Enterprise::withoutGlobalScopes()->updateOrCreate(
                ['code' => $row['code'], 'team_id' => $team->id],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'type_id' => 1,
                    'status_id' => 1,
                    'responsible_id' => $ownerId,
                    'phone' => '34'.(620000000 + $index),
                ],
            );
        }

        $firstNames = ['Sofía', 'Héctor', 'Nuria', 'Óscar', 'Clara', 'Iván', 'Marta', 'Rubén', 'Alicia', 'Pablo', 'Beatriz', 'Sergio', 'Raquel', 'Andrés', 'Nerea'];
        $surnames = ['Vega', 'Molina', 'Prieto', 'Cruz', 'Reyes', 'Flores', 'Cabrera', 'Peña', 'León', 'Herrera', 'Aguilar', 'Soto', 'Méndez', 'Guerrero', 'Cano'];

        foreach ($firstNames as $index => $firstName)
        {
            $n = $index + 21;
            $email = strtolower($firstName.'.'.$surnames[$index]).'@cliente'.$n.'.demo';
            $phone = '3460033'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);

            $enterprise = Enterprise::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('code', 'DEMO0'.(21 + ($index % 8)))
                ->first();

            $contact = Contact::withoutGlobalScopes()->updateOrCreate(
                ['email' => $email, 'team_id' => $team->id],
                [
                    'name' => $firstName,
                    'surname' => $surnames[$index],
                    'phone' => $phone,
                    'profile' => 'Responsable de cuenta',
                    'creator_id' => $ownerId,
                    'responsible_id' => $ownerId,
                    'status_id' => 5,
                    'country' => 724,
                    'language' => 'es',
                    'engagment' => collect(['cold', 'temperate', 'hot'])->random(),
                    'current_enterprise_id' => $enterprise?->id,
                    'user_id' => $ownerId,
                ],
            );

            if ($enterprise !== null)
            {
                DB::table('contact_enterprise')->updateOrInsert(
                    ['contact_id' => $contact->id, 'enterprise_id' => $enterprise->id],
                    ['position' => 'Contacto comercial', 'created_at' => now(), 'updated_at' => now()],
                );

                if ($index % 3 === 0)
                {
                    $this->upsertInvoice(
                        $team,
                        $enterprise,
                        'F-EXT-'.$n.'-A',
                        1200 + ($index * 100),
                        $index % 2 === 0 ? 0 : 1200 + ($index * 100),
                        $index % 2 === 0 ? -30 : 12,
                    );
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function digestScenarios(): array
    {
        return [
            [
                'key' => 'billing_paid_whatsapp',
                'phone' => '34600222001',
                'email' => 'carlos.garcia@cliente1.com',
                'enterprise_code' => 'TECH001',
                'invoices' => [
                    ['number' => 'F-2026-001', 'total' => 2400.00, 'balance' => 0, 'due_days_ago' => 45],
                    ['number' => 'F-2026-002', 'total' => 890.00, 'balance' => 0, 'due_days_ago' => 12],
                ],
                'whatsapp' => 'Hola, querría información sobre facturación.',
                'mailbox' => null,
            ],
            [
                'key' => 'single_overdue_email',
                'phone' => '34600222006',
                'email' => 'laura.sanchez@cliente6.com',
                'enterprise_code' => 'MARK004',
                'invoices' => [
                    ['number' => 'F-2026-060', 'total' => 3250.75, 'balance' => 3250.75, 'due_days_ago' => 22],
                ],
                'whatsapp' => null,
                'mailbox' => [
                    'message_key' => 'digest-laura-overdue',
                    'subject' => 'Estado de factura F-2026-060',
                    'body' => 'Buenos días, ¿podéis confirmar el importe pendiente y enviarme el enlace de pago?',
                    'from' => 'Laura Sánchez <laura.sanchez@cliente6.com>',
                    'hours_ago' => 2,
                ],
            ],
            [
                'key' => 'multiple_unpaid_whatsapp',
                'phone' => '34600222007',
                'email' => 'miguel.hernandez@cliente7.com',
                'enterprise_code' => 'CONS003',
                'invoices' => [
                    ['number' => 'F-2026-071', 'total' => 1500.00, 'balance' => 1500.00, 'due_days_ago' => -8],
                    ['number' => 'F-2026-072', 'total' => 980.50, 'balance' => 980.50, 'due_days_ago' => 18],
                    ['number' => 'F-2026-073', 'total' => 450.00, 'balance' => 450.00, 'due_days_ago' => 6],
                ],
                'whatsapp' => 'Hola, necesito el detalle de las facturas pendientes de pago.',
                'mailbox' => null,
            ],
            [
                'key' => 'schedule_call_whatsapp',
                'phone' => '34600222004',
                'email' => 'ana.lopez@cliente4.com',
                'enterprise_code' => 'DIGI002',
                'invoices' => [
                    ['number' => 'F-2026-040', 'total' => 500.00, 'balance' => 0, 'due_days_ago' => 35],
                ],
                'whatsapp' => '¿Podemos agendar una llamada mañana?',
                'mailbox' => null,
            ],
            [
                'key' => 'billing_no_enterprise_email',
                'phone' => '34600222018',
                'email' => 'patricia.ortiz@cliente18.com',
                'enterprise_code' => null,
                'invoices' => [],
                'whatsapp' => null,
                'mailbox' => [
                    'message_key' => 'digest-patricia-billing',
                    'subject' => 'Información sobre facturación',
                    'body' => 'Hola, querría información sobre facturación para nuestra nueva sociedad.',
                    'from' => 'Patricia Ortiz <patricia.ortiz@cliente18.com>',
                    'hours_ago' => 1,
                ],
            ],
            [
                'key' => 'idoneo_overdue_email',
                'phone' => '34600222999',
                'email' => 'contabilidad@idoneo.es',
                'enterprise_email' => 'hola@idoneo.dev',
                'enterprise_name' => 'IDONEO',
                'invoices' => [
                    ['number' => 'F-IDO-2026-01', 'total' => 4820.00, 'balance' => 4820.00, 'due_days_ago' => 35],
                ],
                'whatsapp' => null,
                'mailbox' => [
                    'message_key' => 'digest-idoneo-overdue',
                    'subject' => 'Re: Factura pendiente — IDONEO',
                    'body' => 'Buenos días, adjuntamos el detalle de la factura pendiente de revisión. ¿Podéis confirmar fecha de pago?',
                    'from' => 'contabilidad@idoneo.es',
                    'hours_ago' => 1,
                ],
            ],
            [
                'key' => 'revision_alpha_billing_email',
                'phone' => '34600123456',
                'email' => 'administracion@revisionalpha.com',
                'enterprise_email' => 'info@revisionalpha.es',
                'enterprise_name' => 'REVISION ALPHA',
                'invoices' => [
                    ['number' => 'F-REV-2026-12', 'total' => 1840.00, 'balance' => 1840.00, 'due_days_ago' => 9],
                ],
                'whatsapp' => null,
                'mailbox' => [
                    'message_key' => 'digest-revision-alpha-admin',
                    'subject' => 'Consulta facturación pendiente',
                    'body' => 'Buenos días, ¿podéis enviarme el detalle de las facturas impagas y los links de pago?',
                    'from' => 'Marina <administracion@revisionalpha.com>',
                    'hours_ago' => 1,
                ],
            ],
            [
                'key' => 'revision_alpha_partial_whatsapp',
                'phone' => '34600222998',
                'email' => 'finanzas@revisionalpha.es',
                'enterprise_email' => 'info@revisionalpha.es',
                'enterprise_name' => 'REVISION ALPHA',
                'invoices' => [
                    ['number' => 'F-REV-2026-10', 'total' => 2100.00, 'balance' => 1050.00, 'due_days_ago' => 14],
                    ['number' => 'F-REV-2026-11', 'total' => 750.00, 'balance' => 0, 'due_days_ago' => 40],
                ],
                'whatsapp' => '¿Tenéis constancia del pago de la factura F-REV-2026-10? El resto está liquidado.',
                'mailbox' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function applyScenario(Team $team, int $ownerId, array $scenario): void
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where(function ($query) use ($scenario): void
            {
                $query->where('phone', $scenario['phone'])
                    ->orWhere('email', $scenario['email']);
            })
            ->first();

        if ($contact === null)
        {
            $contact = Contact::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => explode('@', (string) $scenario['email'])[0],
                'surname' => 'Demo',
                'email' => $scenario['email'],
                'phone' => $scenario['phone'],
                'creator_id' => $ownerId,
                'responsible_id' => $ownerId,
                'status_id' => 5,
                'country' => 724,
                'language' => 'es',
                'engagment' => 'temperate',
                'user_id' => $ownerId,
            ]);
        } else
        {
            $contact->update([
                'phone' => $scenario['phone'],
                'email' => $scenario['email'],
            ]);
        }

        $enterprise = $this->resolveEnterprise($team, $scenario);

        if ($enterprise !== null)
        {
            $contact->update(['current_enterprise_id' => $enterprise->id]);
            DB::table('contact_enterprise')->updateOrInsert(
                ['contact_id' => $contact->id, 'enterprise_id' => $enterprise->id],
                ['position' => 'Contacto principal', 'created_at' => now(), 'updated_at' => now()],
            );
        } elseif (array_key_exists('enterprise_code', $scenario) && $scenario['enterprise_code'] === null)
        {
            $contact->update(['current_enterprise_id' => null]);
        }

        foreach ($scenario['invoices'] as $invoiceRow)
        {
            if ($enterprise === null)
            {
                continue;
            }

            $this->upsertInvoice(
                $team,
                $enterprise,
                $invoiceRow['number'],
                (float) $invoiceRow['total'],
                (float) $invoiceRow['balance'],
                (int) $invoiceRow['due_days_ago'],
            );
        }

        if (! empty($scenario['whatsapp']))
        {
            $this->seedWhatsAppMessage($team, $scenario['phone'], (string) $scenario['whatsapp'], (string) $scenario['key']);
        }

        if (! empty($scenario['mailbox']))
        {
            $this->seedMailboxEmail($team, $scenario['mailbox']);
        }
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function resolveEnterprise(Team $team, array $scenario): ?Enterprise
    {
        if (! empty($scenario['enterprise_email']))
        {
            return Enterprise::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('email', $scenario['enterprise_email'])
                ->first();
        }

        if (! empty($scenario['enterprise_code']))
        {
            return Enterprise::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('code', $scenario['enterprise_code'])
                ->first();
        }

        if (! empty($scenario['enterprise_name']))
        {
            return Enterprise::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('name', $scenario['enterprise_name'])
                ->first();
        }

        return null;
    }

    private function upsertInvoice(
        Team $team,
        Enterprise $enterprise,
        string $number,
        float $total,
        float $balance,
        int $dueDaysAgo,
    ): Invoice {
        $billing = EnterpriseBillingAddress::firstOrCreate(
            ['enterprise_id' => $enterprise->id],
            [
                'name' => $enterprise->name,
                'identification_number' => 'B'.str_pad((string) $enterprise->id, 8, '0', STR_PAD_LEFT),
                'tax_status_type_id' => EnterpriseTaxStatusType::query()->value('id') ?? 1,
                'address' => 'Calle Demo 1',
                'postal_code' => '28001',
                'locality' => 'Madrid',
                'province' => 'Madrid',
                'country' => 'ES',
                'status' => 1,
            ],
        );

        $invoiceDate = now()->subDays(max(1, abs($dueDaysAgo) + 15));
        $dueDate = now()->subDays($dueDaysAgo);
        $isPaid = $balance <= 0.009;

        $invoice = Invoice::withoutGlobalScopes()->updateOrCreate(
            ['number' => $number, 'team_id' => $team->id],
            [
                'enterprise_id' => $enterprise->id,
                'billing_id' => $billing->id,
                'type_id' => InvoiceType::query()->value('id') ?? 1,
                'operation' => 'sell',
                'date' => $invoiceDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'gross_amount' => round($total / 1.21, 2),
                'discount' => 0,
                'total_amount' => $total,
                'balance' => $balance,
                'status' => $isPaid ? 1 : 2,
            ],
        );

        return $invoice;
    }

    private function seedWhatsAppMessage(Team $team, string $peerPhone, string $body, string $scenarioKey): void
    {
        $line = self::DEMO_WHATSAPP_LINE;
        $at = now()->subMinutes(15);

        Conversation::query()->updateOrCreate(
            ['message_sid' => 'SM_DEMO_DIGEST_'.$scenarioKey],
            [
                'channel' => 'whatsapp',
                'from' => $peerPhone,
                'to' => $line,
                'body' => $body,
                'status' => 'received',
                'direction' => 'inbound',
                'created_at' => $at,
                'updated_at' => $at,
            ],
        );
    }

    /**
     * @param  array{message_key: string, subject: string, body: string, from: string, hours_ago: int}  $fixture
     */
    private function seedMailboxEmail(Team $team, array $fixture): void
    {
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

        $messageDate = now()->subHours($fixture['hours_ago']);

        Email::query()->updateOrCreate(
            [
                'mailbox_id' => $mailbox->id,
                'message_id' => 'demo-mailbox-'.$fixture['message_key'],
            ],
            [
                'team_id' => $team->id,
                'subject' => $fixture['subject'],
                'body_text' => $fixture['body'],
                'from_address' => $fixture['from'],
                'to_address' => 'demo-inbox@humano.app',
                'message_date' => $messageDate,
                'seen' => false,
                'flagged' => true,
                'folder' => EmailFolder::Inbox->value,
            ],
        );
    }
}
