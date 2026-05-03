<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTeamSocialNetworksTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_team_social_networks_page_is_public(): void
    {
        $response = $this->get(route('help.team-social-networks'));

        $response->assertOk();
        $response->assertSee('Humano can connect', false);
    }
}
