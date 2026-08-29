<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Module;
use App\Models\Team;
use App\Models\Template;
use App\Services\Mail\CampaignMessageApiService;
use App\Support\DemoTeam;
use App\Support\MailerPresetCatalog;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rich Mailer demo: preset templates with images, extra contacts, news and fake deliveries.
 * Deliveries are historical rows only — never queued or mailed.
 */
class MailerDemoSeeder extends Seeder
{
    public const CONTACT_COUNT = 40;

    public function run(): void
    {
        $teamId = (int) (env('MAILER_DEMO_TEAM_ID') ?: 0);
        $team = $teamId > 0
            ? Team::withoutGlobalScopes()->find($teamId)
            : Team::withoutGlobalScopes()->where('name', DemoTeam::TEAM_NAME)->first();

        if (! $team)
        {
            $this->command?->warn('Demo team not found. Set MAILER_DEMO_TEAM_ID or run the main seed first.');

            return;
        }

        self::seed($team, $this->command);
    }

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
            $contactsModule = Module::query()->create([
                'name' => 'Contacts',
                'key' => 'contacts',
                'icon' => 'users',
                'description' => 'Contacts',
                'is_core' => false,
                'status' => 1,
            ]);
            $contactsModuleId = (int) $contactsModule->id;
        }

        $leadStatus = ContactStatus::query()->where('name', 'Lead')->first();
        if (! $leadStatus)
        {
            $leadStatus = new ContactStatus;
            $leadStatus->name = 'Lead';
            $leadStatus->label_class = 'bg-label-success';
            $leadStatus->save();
        }

        $category = Category::query()->firstOrCreate(
            [
                'team_id' => $teamId,
                'module_id' => $contactsModuleId,
                'name' => 'Newsletter',
            ],
            [
                'description' => 'Audiencia demo de Mailer (no envía)',
                'parent_id' => null,
                'status' => 1,
            ],
        );

        $contactIds = self::seedContacts($teamId, $ownerId, (int) $leadStatus->id, $category);
        $templates = self::seedTemplates($teamId, $command);
        $defaultTemplateId = $templates[0] ?? null;

        foreach (MailerPresetCatalog::news() as $news)
        {
            $message = Message::withoutGlobalScopes()->updateOrCreate(
                [
                    'team_id' => $teamId,
                    'name' => $news['name'],
                ],
                [
                    'text' => $news['text'],
                    'mail_html' => $news['html'],
                    'type_id' => 1,
                    'template_id' => $defaultTemplateId,
                    'category_id' => $category->id,
                    'contact_status_id' => $leadStatus->id,
                    'enable_open_tracking' => true,
                    'enable_click_tracking' => true,
                    'show_unsubscribe' => true,
                    'status_id' => false,
                    'started_at' => null,
                    'scheduled_send_at' => null,
                    'min_hours_between_emails' => 0,
                ],
            );

            $message->syncMessageCategories([$category->id]);

            self::seedFakeDeliveries($teamId, $message, $contactIds, $news['profile']);

            app(CampaignMessageApiService::class)->computeAndPersistStats($message->fresh());
        }

        $command?->info('✅ Mailer demo: '.count($templates).' plantillas, '.count($contactIds).' contactos, '.count(MailerPresetCatalog::news()).' news con envíos fake (sin mail).');
    }

    /**
     * @return list<int>
     */
    private static function seedContacts(int $teamId, int $ownerId, int $leadStatusId, Category $category): array
    {
        $ids = [];

        foreach (MailerPresetCatalog::contacts() as $index => $person)
        {
            $slug = Str::slug($person['name'].'-'.$person['surname']);
            $email = 'mailer-demo-'.$teamId.'-'.$slug.'@fake.com';

            $contact = Contact::withoutGlobalScopes()->firstOrCreate(
                [
                    'team_id' => $teamId,
                    'email' => $email,
                ],
                [
                    'name' => $person['name'],
                    'surname' => $person['surname'],
                    'phone' => '6110000'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'language' => 'es',
                    'country' => 724,
                    'creator_id' => $ownerId,
                    'responsible_id' => $ownerId,
                    'status_id' => $leadStatusId,
                    'birthday' => now()->subYears(28 + ($index % 20)),
                    'profile' => $person['profile'],
                ],
            );

            if (! $category->contacts()->where('contacts.id', $contact->id)->exists())
            {
                $category->contacts()->attach($contact->id);
            }

            $ids[] = (int) $contact->id;
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private static function seedTemplates(int $teamId, ?Command $command): array
    {
        $ids = [];

        foreach (MailerPresetCatalog::templates() as $preset)
        {
            $template = Template::withoutGlobalScopes()->updateOrCreate(
                [
                    'team_id' => $teamId,
                    'name' => $preset['name'],
                ],
                [
                    'status_id' => 1,
                    'gjs_data' => [
                        'html' => $preset['html'],
                        'css' => $preset['css'],
                        'styles' => [],
                        'components' => [],
                    ],
                ],
            );

            $ids[] = (int) $template->id;
            $command?->info('✅ Plantilla demo: '.$template->name);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $contactIds
     * @param  array{failed: int, sent_only: int, delivered: int, opened: int, clicked: int, unsent: int}  $profile
     */
    private static function seedFakeDeliveries(int $teamId, Message $message, array $contactIds, array $profile): void
    {
        $needed = $profile['failed'] + $profile['sent_only'] + $profile['delivered'] + $profile['unsent'];
        if ($needed === 0)
        {
            MessageDelivery::withoutGlobalScopes()
                ->where('message_id', $message->id)
                ->where('email_provider', 'demo-fake')
                ->delete();

            return;
        }

        $sentAt = now()->subDays(4);
        $offset = 0;

        $write = function (array $ids, array $payload) use ($teamId, $message): void
        {
            foreach ($ids as $contactId)
            {
                MessageDelivery::query()->updateOrCreate(
                    [
                        'message_id' => $message->id,
                        'contact_id' => $contactId,
                        'campaign_id' => null,
                    ],
                    array_merge([
                        'team_id' => $teamId,
                        'scheduled_for' => null,
                        'email_provider' => 'demo-fake',
                        'error_message' => null,
                    ], $payload),
                );
            }
        };

        $write(array_slice($contactIds, $offset, $profile['failed']), [
            'status_id' => 4,
            'sent_at' => $sentAt,
            'delivered_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
            'error_message' => 'Demo bounce (fake, no send)',
            'error_type' => 'bounce',
            'bounce_type' => 'soft',
        ]);
        $offset += $profile['failed'];

        $write(array_slice($contactIds, $offset, $profile['sent_only']), [
            'status_id' => 2,
            'sent_at' => $sentAt,
            'delivered_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
        ]);
        $offset += $profile['sent_only'];

        $deliveredIds = array_slice($contactIds, $offset, $profile['delivered']);
        foreach ($deliveredIds as $index => $contactId)
        {
            MessageDelivery::query()->updateOrCreate(
                [
                    'message_id' => $message->id,
                    'contact_id' => $contactId,
                    'campaign_id' => null,
                ],
                [
                    'team_id' => $teamId,
                    'status_id' => 2,
                    'scheduled_for' => null,
                    'email_provider' => 'demo-fake',
                    'sent_at' => $sentAt,
                    'delivered_at' => $sentAt->copy()->addMinutes(8),
                    'opened_at' => $index < $profile['opened'] ? $sentAt->copy()->addMinutes(40) : null,
                    'clicked_at' => $index < $profile['clicked'] ? $sentAt->copy()->addMinutes(55) : null,
                    'error_message' => null,
                ],
            );
        }
        $offset += $profile['delivered'];

        $write(array_slice($contactIds, $offset, $profile['unsent']), [
            'status_id' => 4,
            'sent_at' => null,
            'delivered_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
            'error_message' => 'Demo not sent (fake)',
        ]);
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
