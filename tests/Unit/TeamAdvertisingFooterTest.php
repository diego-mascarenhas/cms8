<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamAdvertisingFooterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function advertising_footer_is_hidden_by_default_even_with_system_smtp(): void
    {
        config(['emailer.show_advertising_footer' => false]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $this->assertTrue($team->isUsingSystemSmtp());
        $this->assertSame('', $team->getAdvertisingFooter());
    }

    #[Test]
    public function advertising_footer_shows_when_enabled_and_using_system_smtp(): void
    {
        config(['emailer.show_advertising_footer' => true]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $footer = $team->getAdvertisingFooter();

        $this->assertStringContainsString('REVISION ALPHA Mailer', $footer);
    }
}
