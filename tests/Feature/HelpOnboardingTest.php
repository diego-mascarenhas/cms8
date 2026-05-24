<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpOnboardingTest extends TestCase
{
    public function test_help_onboarding_page_is_public_and_shows_three_steps(): void
    {
        $response = $this->get(route('help.onboarding'));

        $response->assertStatus(200);
        $response->assertSee(__('help_onboarding.title'), false);
        $response->assertSee(route('pricing'), false);
        $response->assertSee(__('help_onboarding.step2_access'), false);
        $response->assertSee(route('registration.onboarding.qr'), false);
    }

    public function test_help_index_links_to_onboarding_guide(): void
    {
        $response = $this->get(route('help.index'));

        $response->assertStatus(200);
        $response->assertSee(route('help.onboarding'), false);
        $response->assertSee(__('help_onboarding.index_card_title'), false);
    }
}
