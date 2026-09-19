<?php

namespace Tests\Feature\Communications;

use App\Enums\CommunicationStatus;
use App\Jobs\SendCommunicationJob;
use App\Mail\CommunicationMail;
use App\Models\Communication;
use App\Models\MailerUsageLog;
use App\Models\User;
use App\Services\Communications\CommunicationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SendCommunicationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_job_sends_mail_and_marks_sent(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->setSetting('mail_from_address', 'billing@example.test');
        $team->setSetting('mail_from_name', 'Billing');
        $communication = Communication::factory()->forTeamAndUser($team, $user)->email()->create([
            'recipient_email' => 'ada@example.test',
            'subject' => 'Invoice',
            'message' => 'Your invoice is ready.',
        ]);

        (new SendCommunicationJob($communication->id))->handle(app(CommunicationSender::class));

        Mail::assertSent(CommunicationMail::class, function (CommunicationMail $mail) use ($communication)
        {
            return $mail->hasTo('ada@example.test')
                && $mail->communication->is($communication);
        });

        $communication->refresh();
        $this->assertSame(CommunicationStatus::Sent, $communication->status);
        $this->assertNotNull($communication->sent_at);
        $this->assertNull($communication->error_message);
        $this->assertDatabaseHas('mailer_usage_logs', [
            'team_id' => $team->id,
            'source' => 'communications',
            'count' => 1,
        ]);
        $this->assertSame(1, MailerUsageLog::query()->where('team_id', $team->id)->sum('count'));
    }

    public function test_email_job_fails_without_sender(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $communication = Communication::factory()->forTeamAndUser($team, $user)->email()->create();

        try
        {
            app(CommunicationSender::class)->send($communication->fresh(['team']));
            $this->fail('Expected missing sender to throw.');
        } catch (RuntimeException $exception)
        {
            $this->assertStringContainsString('remitente', $exception->getMessage());
        }

        Mail::assertNothingSent();
    }

    public function test_email_job_uses_mailbaby_when_enabled(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Mail::fake();
        Http::fake([
            'https://api.mailbaby.net/mail/send' => Http::response(['id' => 'mb-comm-1'], 200),
        ]);

        config([
            'services.mailbaby.enabled' => true,
            'services.mailbaby.api_key' => 'test-key',
            'services.mailbaby.api_url' => 'https://api.mailbaby.net',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->setSetting('mail_from_address', 'billing@example.test');
        $team->setSetting('mail_from_name', 'Billing');
        $communication = Communication::factory()->forTeamAndUser($team, $user)->email()->create([
            'recipient_email' => 'ada@example.test',
            'subject' => 'Invoice',
            'message' => 'Your invoice is ready.',
        ]);

        (new SendCommunicationJob($communication->id))->handle(app(CommunicationSender::class));

        Mail::assertNothingSent();
        Http::assertSent(function ($request)
        {
            return $request->url() === 'https://api.mailbaby.net/mail/send'
                && $request['to'] === 'ada@example.test'
                && str_contains((string) $request['from'], 'billing@example.test');
        });

        $this->assertSame(CommunicationStatus::Sent, $communication->fresh()->status);
    }

    public function test_whatsapp_job_does_not_record_mailer_usage(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $communication = Communication::factory()->forTeamAndUser($team, $user)->whatsapp()->create();

        $communication->markSent();

        $this->assertSame(CommunicationStatus::Sent, $communication->fresh()->status);
        $this->assertSame(0, MailerUsageLog::query()->where('team_id', $team->id)->count());
    }

    public function test_job_failed_marks_communication_failed(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $communication = Communication::factory()->forTeamAndUser($team, $user)->email()->create();

        $job = new SendCommunicationJob($communication->id);
        $job->failed(new RuntimeException('SMTP down'));

        $communication->refresh();
        $this->assertSame(CommunicationStatus::Failed, $communication->status);
        $this->assertSame('SMTP down', $communication->error_message);
    }

    public function test_job_rethrows_sender_errors_for_retry(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $communication = Communication::factory()->forTeamAndUser($team, $user)->whatsapp()->create();

        $sender = Mockery::mock(CommunicationSender::class);
        $sender->shouldReceive('send')->once()->andThrow(new RuntimeException('Twilio timeout'));

        try
        {
            (new SendCommunicationJob($communication->id))->handle($sender);
            $this->fail('Expected the sender error to be rethrown.');
        } catch (RuntimeException $exception)
        {
            $this->assertSame('Twilio timeout', $exception->getMessage());
        }

        $communication->refresh();
        $this->assertSame(CommunicationStatus::Pending, $communication->status);
        $this->assertSame('Twilio timeout', $communication->error_message);
    }
}
