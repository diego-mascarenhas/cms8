<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpTeamBillingDocumentationTest extends TestCase
{
    public function test_team_billing_help_page_is_public(): void
    {
        $response = $this->get('/help/team-billing');

        $response->assertOk();
        $response->assertSee('Facturación de consumo por equipo', false);
        $response->assertSee('/account-management/{id}/rates', false);
        $response->assertSee('Tokens IA', false);
        $response->assertSee('Chat, Projects, Insights', false);
        $response->assertSee('billing:set-team-rate', false);
        $response->assertSee('¿Cambiar facturación?', false);
        $response->assertSee(route('help.stripe-webhook', [], false), false);
        $response->assertSee(route('manual.billing', [], false), false);
    }

    public function test_rates_page_links_to_team_billing_help(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee(route('help.team-billing', [], false), false)
            ->assertSee('Tarifas de consumo', false);
    }
}
