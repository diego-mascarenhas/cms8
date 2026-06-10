<?php

namespace Tests\Feature;

use App\Mail\SlashLandingInterestMail;
use App\Support\ApplicationLocales;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SlashLandingLeadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.public_home_route' => 'slash',
            'app.public_home_path' => null,
            'app.notification_email' => 'leads@humano.test',
        ]);

        app()->setLocale(ApplicationLocales::DEFAULT);
    }

    public function test_store_lead_sends_notification_email_and_redirects_to_pricing_section(): void
    {
        Mail::fake();

        $this->post(route('slash.lead.store'), [
            'email' => 'interesado@example.com',
            'source' => 'cta',
        ])
            ->assertRedirect(route('slash').'#precios')
            ->assertSessionHas('slash_lead_sent', true);

        Mail::assertSent(SlashLandingInterestMail::class, function (SlashLandingInterestMail $mail): bool
        {
            return $mail->hasTo('leads@humano.test')
                && $mail->leadEmail === 'interesado@example.com'
                && $mail->sourceLabel === __('slash_landing.lead.sources.cta')
                && $mail->leadName === null
                && $mail->leadPhone === null;
        });
    }

    public function test_store_lead_accepts_optional_name_and_phone(): void
    {
        Mail::fake();

        $this->post(route('slash.lead.store'), [
            'email' => 'maria@example.com',
            'source' => 'hero',
            'name' => 'María',
            'phone' => '+34 624 15 95 57',
        ])
            ->assertRedirect(route('slash').'#precios');

        Mail::assertSent(SlashLandingInterestMail::class, function (SlashLandingInterestMail $mail): bool
        {
            return $mail->leadEmail === 'maria@example.com'
                && $mail->leadName === 'María'
                && $mail->leadPhone === '+34 624 15 95 57';
        });
    }

    public function test_store_lead_requires_valid_email(): void
    {
        Mail::fake();

        $this->from(route('slash'))
            ->post(route('slash.lead.store'), [
                'email' => 'not-an-email',
                'source' => 'hero',
            ])
            ->assertRedirect(route('slash'))
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_store_lead_returns_404_when_slash_is_not_public_home(): void
    {
        config([
            'app.public_home_route' => null,
            'app.public_home_path' => null,
        ]);

        $this->post(route('slash.lead.store'), [
            'email' => 'interesado@example.com',
        ])
            ->assertNotFound();
    }
}
