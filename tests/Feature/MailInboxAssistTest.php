<?php

namespace Tests\Feature;

use App\Livewire\MailInbox;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailInboxAssistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    public function test_the_list_shows_an_assistant_action(): void
    {
        $user = $this->userWithTeam();
        $this->createEmail($user->currentTeam, [
            'from_address' => 'Ana <ana@example.com>',
            'subject' => 'Consulta',
        ]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->assertSeeHtml('ti-sparkles')
            ->assertSeeHtml('assistEmail');
    }

    public function test_assistant_on_a_hard_bounce_clears_the_address_and_does_not_draft_a_reply(): void
    {
        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        Contact::factory()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'email' => 'gone@example.test',
        ]);
        $email = $this->createEmail($team, [
            'from_address' => 'mailer-daemon@example.test',
            'subject' => 'Undelivered Mail Returned to Sender',
            'body_text' => "Final-Recipient: rfc822; gone@example.test\nStatus: 5.1.1\nDiagnostic-Code: smtp; 550 5.1.1 No such user",
        ]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('assistEmail', $email->id)
            ->assertNotDispatched('open-mail-compose')
            ->assertSet('statusType', 'success');

        $this->assertNull(Contact::withoutGlobalScopes()->where('team_id', $team->id)->where('id', '>', 0)->whereRaw('LOWER(email) = ?', ['gone@example.test'])->value('email'));
        $contact = Contact::withoutGlobalScopes()->where('team_id', $team->id)->first();
        $this->assertNull($contact->email);
        $this->assertStringContainsString('Rebote duro', (string) data_get($contact->data, 'notes'));
    }

    public function test_assistant_opens_a_suggested_reply_for_a_person(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, [
            'from_address' => 'Ana <ana@example.com>',
            'subject' => 'Consulta',
            'body_text' => '¿Me pasás el precio?',
        ]);

        Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('assistEmail', $email->id)
            ->assertDispatched('open-mail-compose', recipients: ['ana@example.com'], subject: 'Re: Consulta', suggest: true);
    }

    public function test_assistant_on_an_invoice_opens_the_expense_form(): void
    {
        $user = $this->userWithTeam();
        $email = $this->createEmail($user->currentTeam, [
            'from_address' => 'Proveedor <proveedor@example.com>',
            'subject' => 'Factura A-8841',
            'body_text' => "Fecha: 26/10/2026\nTotal: 150,00 EUR\nHosting anual",
        ]);

        $component = Livewire::actingAs($user)
            ->test(MailInbox::class)
            ->call('assistEmail', $email->id);

        $component->assertRedirect();
        $target = (string) ($component->effects['redirect'] ?? '');
        $this->assertStringContainsString('/expense/create', $target);
        $this->assertStringContainsString('document_number=A-8841', urldecode($target));
        $this->assertStringContainsString('base_amount=150.00', urldecode($target));
        $this->assertStringContainsString('currency=EUR', urldecode($target));
        $this->assertStringContainsString('date=2026-10-26', urldecode($target));
    }

    private function userWithTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function createEmail(Team $team, array $attributes = []): Email
    {
        $mailbox = Mailbox::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
        ]);

        return Email::factory()->create(array_merge([
            'mailbox_id' => $mailbox->id,
            'team_id' => $team->id,
        ], $attributes));
    }
}
