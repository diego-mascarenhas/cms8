<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaigns_placeholder_page_is_reachable_when_authenticated(): void
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
            str_contains($html, 'Coming Soon')
            || str_contains($html, 'Próximamente'),
        );
    }
}
