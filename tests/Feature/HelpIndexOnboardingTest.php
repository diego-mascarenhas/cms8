<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpIndexOnboardingTest extends TestCase
{
    public function test_help_index_shows_onboarding_section_in_spanish_default_locale(): void
    {
        $response = $this->get(route('help.index'));

        $response->assertStatus(200);
        $response->assertSee(route('help.onboarding'), false);
        $response->assertSee('Onboarding: registro y primeros pasos', false);
        $response->assertSee('/register', false);
        $response->assertSee('/registration/onboarding/qr', false);
    }

    public function test_onboarding_hides_payment_step_when_registration_is_free(): void
    {
        config(['registration.mode' => 'free']);

        $response = $this->get(route('help.index'));

        $response->assertStatus(200);
        $response->assertDontSee('/registration/billing', false);
        $response->assertDontSee('Completar el pago del alta', false);
    }

    public function test_onboarding_shows_payment_step_when_registration_requires_checkout(): void
    {
        config(['registration.mode' => 'gate']);

        $response = $this->get(route('help.index'));

        $response->assertStatus(200);
        $response->assertSee('/registration/billing', false);
        $response->assertSee('Completar el pago del alta', false);
    }
}
