<?php

namespace Tests\Feature;

use App\Livewire\Landing\BusinessWizard;
use App\Mail\BusinessCreationReportMail;
use App\Models\BusinessCreationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class LandingBusinessWizardEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_sends_report_to_user_and_bcc_to_mail_from_address(): void
    {
        Mail::fake();
        Config::set('mail.from.address', 'copy@humano.test');

        $session = BusinessCreationSession::createWithToken();
        $session->update([
            'config' => [
                'contact_email' => 'lead@example.com',
                '_summary' => 'Resumen listo',
                '_insights' => ['potential_clients_summary' => 'Informe listo'],
            ],
            'current_step' => 6,
        ]);

        Livewire::test(BusinessWizard::class, ['token' => $session->token])
            ->call('submit')
            ->assertRedirect(route('landing.gracias'));

        Mail::assertSent(BusinessCreationReportMail::class, function (BusinessCreationReportMail $mail): bool
        {
            return $mail->hasTo('lead@example.com') && $mail->hasBcc('copy@humano.test');
        });
    }
}
