<?php

namespace Tests\Feature\Communications;

use App\Enums\CommunicationStatus;
use App\Jobs\SendCommunicationJob;
use App\Mail\CommunicationMail;
use App\Models\Communication;
use App\Models\User;
use App\Services\Communications\CommunicationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
