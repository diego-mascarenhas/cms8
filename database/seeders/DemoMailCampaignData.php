<?php

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Module;
use App\Models\Team;
use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demo fixtures for mail campaigns (broadcast, sequence) and standalone messages.
 * Expects Team "Demo" seed (categories Tester, template, message types) — see {@see TeamDemoSeeder}.
 */
final class DemoMailCampaignData
{
    /** Minimum contacts per demo newsletter / campaign audience (must match UI expectations for simulations). */
    public const DEMO_NEWSLETTER_CONTACT_COUNT = 12;

    public static function seed(Team $team, ?Command $command = null): void
    {
        $teamId = (int) $team->id;
        $ownerId = (int) ($team->user_id ?? 0);
        if ($ownerId === 0)
        {
            $firstMember = $team->users()->first();
            $ownerId = $firstMember ? (int) $firstMember->id : 1;
        }

        self::ensureMessageTypes($command);

        $contactsModuleId = Module::query()->where('key', 'contacts')->value('id');
        if (! $contactsModuleId)
        {
            $command?->warn('Contacts module missing; skip demo mail campaign seed.');

            return;
        }

        $testerCategory = Category::query()
            ->where('team_id', $teamId)
            ->where('module_id', $contactsModuleId)
            ->where('name', 'Tester')
            ->first();

        if (! $testerCategory)
        {
            $testerCategory = Category::query()->create([
                'team_id' => $teamId,
                'module_id' => $contactsModuleId,
                'name' => 'Tester',
                'description' => 'Contactos de prueba (demo mailer)',
                'parent_id' => null,
                'status' => 1,
            ]);
        }

        $leadStatusId = (int) (ContactStatus::query()->where('name', 'Lead')->value('id') ?? 1);

        $template = Template::query()
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->first();

        $demoContacts = self::createOrSyncDemoContacts(
            $teamId,
            $ownerId,
            $leadStatusId,
            $testerCategory,
        );

        $templateId = $template?->id;

        $standalone = Message::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'name' => '[Demo] Mensaje suelto (newsletter)',
            ],
            [
                'text' => '<p>Demo: mensaje <strong>sin campaña</strong>. Categoría Tester, listo para Enviar ahora.</p>',
                'type_id' => 1,
                'template_id' => $templateId,
                'category_id' => $testerCategory->id,
                'contact_status_id' => $leadStatusId,
                'enable_open_tracking' => true,
                'enable_click_tracking' => true,
                'show_unsubscribe' => true,
                'status_id' => true,
                'min_hours_between_emails' => 0,
            ],
        );

        $msgBroadcast = Message::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'name' => '[Demo] Difusión — cuerpo del mail',
            ],
            [
                'text' => '<p>Demo <strong>difusión</strong> vinculada a campaña. Misma audiencia (Tester).</p>',
                'type_id' => 1,
                'template_id' => $templateId,
                'category_id' => $testerCategory->id,
                'contact_status_id' => $leadStatusId,
                'enable_open_tracking' => true,
                'enable_click_tracking' => true,
                'show_unsubscribe' => true,
                'status_id' => true,
                'min_hours_between_emails' => 0,
            ],
        );

        $msgSeqA = Message::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'name' => '[Demo] Secuencia — Paso 1 bienvenida',
            ],
            [
                'text' => '<p>Paso 1 de la secuencia demo.</p>',
                'type_id' => 1,
                'template_id' => $templateId,
                'category_id' => $testerCategory->id,
                'contact_status_id' => $leadStatusId,
                'enable_open_tracking' => true,
                'enable_click_tracking' => true,
                'show_unsubscribe' => true,
                'status_id' => true,
                'min_hours_between_emails' => 0,
            ],
        );

        $msgSeqB = Message::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'name' => '[Demo] Secuencia — Paso 2 seguimiento',
            ],
            [
                'text' => '<p>Paso 2 (tras espera en la campaña).</p>',
                'type_id' => 1,
                'template_id' => $templateId,
                'category_id' => $testerCategory->id,
                'contact_status_id' => $leadStatusId,
                'enable_open_tracking' => true,
                'enable_click_tracking' => true,
                'show_unsubscribe' => true,
                'status_id' => true,
                'min_hours_between_emails' => 0,
            ],
        );

        Message::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereIn('name', [
                '[Demo] Mensaje suelto (newsletter)',
                '[Demo] Difusión — cuerpo del mail',
                '[Demo] Secuencia — Paso 1 bienvenida',
                '[Demo] Secuencia — Paso 2 seguimiento',
            ])
            ->update(['status_id' => true]);

        $campaignBroadcast = Campaign::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'name' => '[Demo] Campaña difusión',
            ],
            [
                'type' => CampaignType::Broadcasts->value,
                'status' => CampaignStatus::Active->value,
                'summary' => 'Demo: un mensaje, audiencia categoría Tester.',
            ],
        );

        $campaignBroadcast->messages()->syncWithoutDetaching([
            $msgBroadcast->id => [
                'sort_order' => 0,
                'delay_minutes_after_previous' => null,
                'conditions' => null,
            ],
        ]);

        $campaignSequence = Campaign::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'name' => '[Demo] Campaña secuencia (2 pasos)',
            ],
            [
                'type' => CampaignType::Sequences->value,
                'status' => CampaignStatus::Active->value,
                'summary' => 'Demo: dos correos, espera 60 min entre pasos.',
            ],
        );

        $campaignSequence->messages()->syncWithoutDetaching([
            $msgSeqA->id => [
                'sort_order' => 0,
                'delay_minutes_after_previous' => null,
                'conditions' => null,
            ],
            $msgSeqB->id => [
                'sort_order' => 1,
                'delay_minutes_after_previous' => 60,
                'conditions' => null,
            ],
        ]);

        self::seedDemoDeliveriesForAllMessages(
            $teamId,
            $demoContacts,
            $standalone,
            $msgBroadcast,
            $msgSeqA,
            $msgSeqB,
            $campaignBroadcast,
            $campaignSequence,
            $command,
        );

        $command?->info('✅ Demo mail: mensaje suelto ID '.$standalone->id);
        $command?->info('✅ Demo mail: difusión campaña ID '.$campaignBroadcast->id.' → mensaje ID '.$msgBroadcast->id);
        $command?->info('✅ Demo mail: secuencia campaña ID '.$campaignSequence->id.' → mensajes ID '.$msgSeqA->id.', '.$msgSeqB->id);
        $command?->info('✅ Demo mail: '.count($demoContacts).' contactos Tester + entregas seed (campaña/newsletter) para estadísticas.');
    }

    /**
     * @return list<int>
     */
    private static function createOrSyncDemoContacts(
        int $teamId,
        int $ownerId,
        int $leadStatusId,
        Category $testerCategory,
    ): array {
        $ids = [];

        foreach (range(1, self::DEMO_NEWSLETTER_CONTACT_COUNT) as $index)
        {
            $email = 'demo-mail-'.$teamId.'-tester-'.$index.'@example.test';

            $contact = Contact::withoutGlobalScopes()->firstOrCreate(
                [
                    'team_id' => $teamId,
                    'email' => $email,
                ],
                [
                    'name' => 'Demo Tester',
                    'surname' => '#'.$index,
                    'phone' => '6000000'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'language' => 'es',
                    'country' => 724,
                    'creator_id' => $ownerId,
                    'responsible_id' => $ownerId,
                    'status_id' => $leadStatusId,
                    'birthday' => now()->subYears(25),
                    'profile' => 'Contacto semilla DemoMailCampaignData (Tester / Lead).',
                ],
            );

            if ((int) $contact->team_id !== $teamId)
            {
                $contact->update(['team_id' => $teamId]);
            }

            if ((int) $contact->status_id !== $leadStatusId)
            {
                $contact->update(['status_id' => $leadStatusId]);
            }

            if (! $testerCategory->contacts()->where('contacts.id', $contact->id)->exists())
            {
                $testerCategory->contacts()->attach($contact->id);
            }

            $ids[] = (int) $contact->id;
        }

        return $ids;
    }

    /**
     * Campaign show stats ({@see \App\Models\Campaign::deliveryStatistics()}) only count
     * {@see MessageDelivery} rows with this campaign_id — seed one row per contact per campaign message.
     * Standalone newsletter rows use campaign_id null (unique per message + contact).
     */
    private static function seedDemoDeliveriesForAllMessages(
        int $teamId,
        array $contactIds,
        Message $standalone,
        Message $msgBroadcast,
        Message $msgSeqA,
        Message $msgSeqB,
        Campaign $campaignBroadcast,
        Campaign $campaignSequence,
        ?Command $command,
    ): void {
        if ($contactIds === [])
        {
            return;
        }

        // status_id 1 = pending (see SendScheduledDeliveries, MessageController). Messages must be active (status_id true)
        // so SendMessageCampaignJob::validateDelivery() allows SMTP/Mailpit sends.
        $pendingPayload = [
            'team_id' => $teamId,
            'status_id' => 1,
            'scheduled_for' => now(),
            'sent_at' => null,
            'delivered_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
        ];

        foreach ($contactIds as $contactId)
        {
            MessageDelivery::query()->updateOrCreate(
                [
                    'message_id' => $standalone->id,
                    'contact_id' => $contactId,
                    'campaign_id' => null,
                ],
                $pendingPayload,
            );

            MessageDelivery::query()->updateOrCreate(
                [
                    'message_id' => $msgBroadcast->id,
                    'contact_id' => $contactId,
                    'campaign_id' => $campaignBroadcast->id,
                ],
                $pendingPayload,
            );

            MessageDelivery::query()->updateOrCreate(
                [
                    'message_id' => $msgSeqA->id,
                    'contact_id' => $contactId,
                    'campaign_id' => $campaignSequence->id,
                ],
                $pendingPayload,
            );

            MessageDelivery::query()->updateOrCreate(
                [
                    'message_id' => $msgSeqB->id,
                    'contact_id' => $contactId,
                    'campaign_id' => $campaignSequence->id,
                ],
                $pendingPayload,
            );
        }

        // A few completed rows on broadcast so "Enviados / Entregados / Abiertos" are non-zero in the UI
        $firstIds = array_slice($contactIds, 0, 3);
        $past = now()->subMinutes(30);
        foreach ($firstIds as $contactId)
        {
            MessageDelivery::query()->updateOrCreate(
                [
                    'message_id' => $msgBroadcast->id,
                    'contact_id' => $contactId,
                    'campaign_id' => $campaignBroadcast->id,
                ],
                [
                    'team_id' => $teamId,
                    'status_id' => 2,
                    'scheduled_for' => null,
                    'sent_at' => $past,
                    'delivered_at' => $past,
                    'opened_at' => $past,
                    'clicked_at' => null,
                ],
            );
        }

        $command?->info('✅ Demo mail: entregas seed — newsletter suelta + difusión + secuencia (2 pasos), '.count($contactIds).' contactos c/u; 3 envíos demo completados en difusión.');
    }

    private static function ensureMessageTypes(?Command $command): void
    {
        foreach (
            [
                ['id' => 1, 'name' => 'Mailer', 'status' => 1],
                ['id' => 2, 'name' => 'WhatsApp', 'status' => 1],
            ] as $type
        ) {
            DB::table('message_type')->updateOrInsert(
                ['id' => $type['id']],
                $type,
            );
        }

        $command?->info('✅ Message types ensured (Mailer / WhatsApp).');
    }
}
