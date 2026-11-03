<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Email;
use App\Models\List60;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Module;
use App\Models\User;
use App\Services\Mail\MailboxInboundActionService;
use App\Support\List60StatusAdvancer;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MailboxInboundActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    public function test_a_hard_bounce_from_the_shared_mailbox_clears_the_address_and_notes_the_contact(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $contact = $this->contact($user, $team, 'gone@example.test');
        $pending = $this->delivery($team, $contact, 1, null);
        $sent = $this->delivery($team, $contact, 2, now());

        $this->handle($mailbox, 'mailer-daemon@example.test', 'Undelivered Mail Returned to Sender', <<<'TXT'
Final-Recipient: rfc822; gone@example.test
Action: failed
Status: 5.1.1
Diagnostic-Code: smtp; 550 5.1.1 No such user
TXT);

        $contact->refresh();
        $this->assertNull($contact->email);
        $this->assertSame('gone@example.test', data_get($contact->data, 'removed_email'));
        $this->assertStringContainsString('Rebote duro: la casilla gone@example.test no existe.', (string) data_get($contact->data, 'notes'));
        $this->assertNull(MessageDelivery::query()->find($pending->id));
        $sent->refresh();
        $this->assertSame('hard', $sent->bounce_type);
        $this->assertNotNull($sent->bounced_at);
    }

    public function test_a_full_mailbox_bounce_keeps_the_address_and_notes_the_contact(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $contact = $this->contact($user, $team, 'full@example.test');

        $this->handle($mailbox, 'mailer-daemon@example.test', 'Mail delivery failed', <<<'TXT'
Final-Recipient: rfc822; full@example.test
Action: failed
Status: 4.2.2
Diagnostic-Code: smtp; 452 4.2.2 Mailbox full
TXT);

        $contact->refresh();
        $this->assertSame('full@example.test', $contact->email);
        $this->assertStringContainsString('Rebote temporal de full@example.test', (string) data_get($contact->data, 'notes'));
        $this->assertStringNotContainsString('no existe', (string) data_get($contact->data, 'notes'));
    }

    public function test_a_policy_rejection_keeps_the_address_and_notes_the_contact(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $contact = $this->contact($user, $team, 'kept@example.test');

        $this->handle($mailbox, 'mailer-daemon@example.test', 'Undelivered Mail Returned to Sender', <<<'TXT'
Final-Recipient: rfc822; kept@example.test
Action: failed
Status: 5.7.1
Diagnostic-Code: smtp; 550 5.7.1 Message rejected
TXT);

        $contact->refresh();
        $this->assertSame('kept@example.test', $contact->email);
        $this->assertStringContainsString('El servidor rechazó el mensaje a kept@example.test', (string) data_get($contact->data, 'notes'));
    }

    public function test_a_bounce_already_classified_by_the_provider_is_left_alone(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $contact = $this->contact($user, $team, 'gone@example.test');
        $this->delivery($team, $contact, 4, now(), 'hard');

        $this->handle($mailbox, 'mailer-daemon@example.test', 'Undelivered Mail Returned to Sender', <<<'TXT'
Final-Recipient: rfc822; gone@example.test
Status: 5.1.1
Diagnostic-Code: smtp; 550 5.1.1 No such user
TXT);

        $contact->refresh();
        $this->assertSame('gone@example.test', $contact->email);
        $this->assertNull(data_get($contact->data, 'notes'));
    }

    public function test_a_human_reply_is_saved_and_the_contact_enters_list_60(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $this->enableList60($team);
        $contact = $this->contact($user, $team, 'ana@example.test');

        $this->handle($mailbox, 'Ana <ana@example.test>', 'Re: Novedades', 'Me interesa, hablamos mañana.');

        $contact->refresh();
        $this->assertStringContainsString('Respondió por email: Me interesa, hablamos mañana.', (string) data_get($contact->data, 'notes'));
        $this->assertSame(1, List60::query()->where('contact_id', $contact->id)->count());
        $this->assertSame($user->id, List60::query()->where('contact_id', $contact->id)->value('responsible_id'));
    }

    public function test_a_second_reply_only_appends_the_note_when_the_contact_is_already_listed(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $this->enableList60($team);
        $contact = $this->contact($user, $team, 'ana@example.test');
        List60::query()->create([
            'contact_id' => $contact->id,
            'type_id' => 1,
            'date_next' => now()->addWeek(),
            'responsible_id' => $user->id,
            'status_id' => 1,
            'notes' => 'Ya estaba.',
        ]);

        $this->handle($mailbox, 'ana@example.test', 'Re: Novedades', 'Sigo por acá.');

        $this->assertSame(1, List60::query()->where('contact_id', $contact->id)->count());
        $record = List60::query()->where('contact_id', $contact->id)->first();
        $this->assertStringContainsString('Ya estaba.', (string) $record->notes);
        $this->assertStringContainsString('Respondió por email: Sigo por acá.', (string) $record->notes);
    }

    public function test_an_automatic_reply_does_not_enter_list_60(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $this->enableList60($team);
        $contact = $this->contact($user, $team, 'ana@example.test');

        $this->handle($mailbox, 'ana@example.test', 'Automatic reply: Out of office', 'Vuelvo el lunes.');

        $contact->refresh();
        $this->assertNull(data_get($contact->data, 'notes'));
        $this->assertSame(0, List60::query()->where('contact_id', $contact->id)->count());
    }

    public function test_a_personal_mailbox_does_not_change_the_contact(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $mailbox->forceFill(['user_id' => $user->id])->save();
        $contact = $this->contact($user, $team, 'gone@example.test');

        $this->handle($mailbox, 'mailer-daemon@example.test', 'Undelivered Mail Returned to Sender', <<<'TXT'
Final-Recipient: rfc822; gone@example.test
Status: 5.1.1
Diagnostic-Code: smtp; 550 5.1.1 No such user
TXT);

        $this->assertSame('gone@example.test', $contact->fresh()->email);
    }

    public function test_the_same_message_is_not_applied_twice(): void
    {
        [$user, $team, $mailbox] = $this->sharedMailbox();
        $contact = $this->contact($user, $team, 'full@example.test');
        $email = Email::factory()->create([
            'mailbox_id' => $mailbox->id,
            'team_id' => $team->id,
            'from_address' => 'mailer-daemon@example.test',
            'subject' => 'Mail delivery failed',
            'body_text' => "Final-Recipient: rfc822; full@example.test\nStatus: 4.2.2\nDiagnostic-Code: smtp; 452 4.2.2 Mailbox full",
        ]);

        $service = app(MailboxInboundActionService::class);
        $service->handle($email);
        $service->handle($email->fresh());

        $contact->refresh();
        $notes = (string) data_get($contact->data, 'notes');
        $this->assertSame(1, substr_count($notes, 'Rebote temporal'));
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: Mailbox}
     */
    private function sharedMailbox(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $mailbox = Mailbox::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'username' => 'info@example.test',
        ]);

        return [$user, $team, $mailbox];
    }

    private function contact(User $user, \App\Models\Team $team, string $email): Contact
    {
        return Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => $email,
        ]);
    }

    private function delivery(\App\Models\Team $team, Contact $contact, int $statusId, mixed $sentAt, ?string $bounceType = null): MessageDelivery
    {
        $message = Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Aviso',
            'text' => 'Asunto',
            'type_id' => 1,
            'status_id' => 1,
            'mail_html' => '<p>Hola</p>',
        ]);

        return MessageDelivery::query()->create([
            'team_id' => $team->id,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'status_id' => $statusId,
            'sent_at' => $sentAt,
            'bounce_type' => $bounceType,
            'bounced_at' => $bounceType === null ? null : now(),
        ]);
    }

    private function enableList60(\App\Models\Team $team): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'Lista 60',
                'icon' => 'list',
                'description' => 'Lista de seguimiento',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('list60');
        DB::table('enterprise_types')->updateOrInsert(['id' => 1], ['name' => 'Cliente']);
        List60StatusAdvancer::initialStatusId();
    }

    private function handle(Mailbox $mailbox, string $from, string $subject, string $body): void
    {
        $email = Email::factory()->create([
            'mailbox_id' => $mailbox->id,
            'team_id' => $mailbox->team_id,
            'from_address' => $from,
            'subject' => $subject,
            'body_text' => $body,
        ]);

        app(MailboxInboundActionService::class)->handle($email);
    }
}
