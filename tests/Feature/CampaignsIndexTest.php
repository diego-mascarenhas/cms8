<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaigns_page_shows_campaign_manager_sections_when_authenticated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.index'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Campaigns')
            || str_contains($html, 'Campañas'),
        );
        $this->assertTrue(
            str_contains($html, 'Email Campaigns'),
        );
        $this->assertTrue(
            str_contains($html, 'Manage Templates'),
        );
        $this->assertTrue(
            str_contains($html, 'New Email Campaign'),
        );
    }
}
