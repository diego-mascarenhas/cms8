<?php

namespace Tests\Feature\Homes;

use App\Mail\SlashLandingInterestMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CmsLandingLeadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.notification_email' => 'leads@humano.test',
        ]);
    }

    public function test_store_lead_sends_notification_email_and_redirects_to_cms_landing(): void
    {
        Mail::fake();

        $this->post(route('cms.lead.store'), [
            'email' => 'interesado@example.com',
            'source' => 'hero',
        ])
            ->assertRedirect(route('cms.landing').'#empezar')
            ->assertSessionHas('slash_lead_sent', true);

        Mail::assertSent(SlashLandingInterestMail::class, function (SlashLandingInterestMail $mail): bool
        {
            return $mail->hasTo('leads@humano.test')
                && $mail->leadEmail === 'interesado@example.com'
                && $mail->sourceLabel === __('cms_landing.lead.sources.hero');
        });
    }

    public function test_store_lead_requires_valid_email(): void
    {
        Mail::fake();

        $this->from(route('cms.landing'))
            ->post(route('cms.lead.store'), [
                'email' => 'not-an-email',
                'source' => 'cta',
            ])
            ->assertRedirect(route('cms.landing'))
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }
}
