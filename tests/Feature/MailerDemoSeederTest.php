<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Module;
use App\Models\Template;
use App\Models\User;
use App\Support\MailerPresetCatalog;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MailerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class MailerDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_templates_contacts_news_and_fake_deliveries_without_sending_mail(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Mail::fake();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => 'Demo'])->save();

        Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'Contacts',
                'is_core' => false,
                'status' => 1,
            ],
        );

        MailerDemoSeeder::seed($team);

        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'like', 'mailer-demo-'.$team->id.'-%@fake.com')
            ->count();
        $this->assertSame(MailerDemoSeeder::CONTACT_COUNT, $contacts);

        $templates = Template::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('name', collect(MailerPresetCatalog::templates())->pluck('name'))
            ->get();
        $this->assertCount(count(MailerPresetCatalog::templates()), $templates);
        $this->assertTrue($templates->every(fn (Template $template) => str_contains((string) $template->html, '<img')));

        foreach (MailerPresetCatalog::news() as $news)
        {
            $message = Message::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('name', $news['name'])
                ->first();
            $this->assertNotNull($message, 'Missing news: '.$news['name']);
            $this->assertFalse((bool) $message->status_id);
            $this->assertNull($message->started_at);
            $this->assertNull($message->scheduled_send_at);
            $this->assertStringContainsString('<img', (string) $message->mail_html);
        }

        $duePending = MessageDelivery::query()
            ->where('email_provider', 'demo-fake')
            ->where('status_id', 1)
            ->where('scheduled_for', '<=', now())
            ->count();
        $this->assertSame(0, $duePending);

        $newsletter = Message::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('name', '[Demo] Newsletter de agosto')
            ->first();
        $this->assertNotNull($newsletter);

        $sent = MessageDelivery::query()
            ->where('message_id', $newsletter->id)
            ->whereNotNull('sent_at')
            ->where('sent_at', '<', now())
            ->count();
        $this->assertSame(38, $sent);

        $draft = Message::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('name', '[Demo] Borrador sin envíos')
            ->first();
        $this->assertSame(0, MessageDelivery::query()->where('message_id', $draft->id)->count());

        Mail::assertNothingSent();
    }
}
