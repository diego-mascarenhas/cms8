<?php

namespace Tests\Feature;

use App\Mail\TeamInvitation;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamInvitationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_invitation_email_renders_humano_layout_without_raw_markdown_html(): void
    {
        $team = Team::factory()->create(['name' => 'Revision Alpha']);
        $invitation = $team->teamInvitations()->create([
            'email' => 'invitee@example.com',
            'role' => 'admin',
        ]);

        $html = (new TeamInvitation($invitation))->render();

        $this->assertStringNotContainsString('<table class="action"', $html);
        $this->assertStringContainsString('logo', $html);
        $this->assertStringContainsString(__('Accept Invitation'), $html);
        $this->assertStringContainsString(__('All rights reserved.'), $html);
        $this->assertStringContainsString('Revision Alpha', $html);
    }
}
