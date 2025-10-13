<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test team
        $user = User::factory()->withPersonalTeam()->create();
        $this->team = $user->currentTeam;
    }

    /** @test */
    public function it_can_set_and_get_settings()
    {
        $this->team->setSetting('test_key', 'test_value', [
            'group' => 'test',
            'is_encrypted' => false,
        ]);

        $this->assertEquals('test_value', $this->team->getSetting('test_key'));
        $this->assertEquals('default', $this->team->getSetting('non_existent', 'default'));
    }

    /** @test */
    public function it_encrypts_sensitive_settings()
    {
        $this->team->setSetting('secret_key', 'sensitive_data', [
            'group' => 'security',
            'is_encrypted' => true,
        ]);

        // Check that the raw value is encrypted
        $setting = TeamSetting::where('team_id', $this->team->id)
            ->where('key', 'secret_key')
            ->first();

        $this->assertNotEquals('sensitive_data', $setting->getAttributes()['value']);
        $this->assertEquals('sensitive_data', $this->team->getSetting('secret_key'));
    }

    /** @test */
    public function it_generates_consistent_team_hash()
    {
        $hash1 = Team::generateTeamHash($this->team->id);
        $hash2 = Team::generateTeamHash($this->team->id);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(12, strlen($hash1));

        // Different team should have different hash
        $differentHash = Team::generateTeamHash($this->team->id + 1);
        $this->assertNotEquals($hash1, $differentHash);
    }

    /** @test */
    public function it_can_find_team_by_webhook_hash()
    {
        $hash = $this->team->getTeamHash();
        $foundTeam = Team::findByWebhookHash($hash);

        $this->assertEquals($this->team->id, $foundTeam->id);

        // Non-existent hash should return null
        $this->assertNull(Team::findByWebhookHash('invalid_hash'));
    }

    /** @test */
    public function it_generates_twilio_webhook_urls()
    {
        $webhookUrl = $this->team->getTwilioWebhookUrl();
        $statusUrl = $this->team->getTwilioStatusCallbackUrl();

        $this->assertStringContainsString(config('app.url'), $webhookUrl);
        $this->assertStringContainsString('/twilio/webhook/', $webhookUrl);
        $this->assertStringContainsString($this->team->getTeamHash(), $webhookUrl);

        $this->assertStringContainsString('/twilio/status/', $statusUrl);
        $this->assertStringContainsString($this->team->getTeamHash(), $statusUrl);
    }

    /** @test */
    public function it_checks_twilio_configuration()
    {
        // Initially no Twilio config
        $this->assertFalse($this->team->hasTwilioConfig());

        // Set partial config
        $this->team->setSetting('twilio_sid', 'AC123456789');
        $this->assertFalse($this->team->hasTwilioConfig());

        // Set complete config
        $this->team->setSetting('twilio_token', 'secret_token', ['is_encrypted' => true]);
        $this->assertTrue($this->team->hasTwilioConfig());
    }

    /** @test */
    public function it_gets_twilio_configuration()
    {
        $this->team->setSetting('twilio_sid', 'AC123456789');
        $this->team->setSetting('twilio_token', 'secret_token', ['is_encrypted' => true]);
        $this->team->setSetting('twilio_sms_from', '+12345678900');
        $this->team->setSetting('twilio_whatsapp_from', '+12345678901');

        $config = $this->team->getTwilioConfig();

        $this->assertEquals('AC123456789', $config['sid']);
        $this->assertEquals('secret_token', $config['token']);
        $this->assertEquals('+12345678900', $config['sms_from']);
        $this->assertEquals('+12345678901', $config['whatsapp_from']);
    }

    /** @test */
    public function it_checks_outgoing_email_configuration()
    {
        // Initially no email config
        $this->assertFalse($this->team->hasOutgoingEmailConfig());

        // Set partial config
        $this->team->setSetting('mail_host', 'smtp.example.com');
        $this->assertFalse($this->team->hasOutgoingEmailConfig());

        // Set complete config
        $this->team->setSetting('mail_username', 'user@example.com');
        $this->team->setSetting('mail_password', 'password', ['is_encrypted' => true]);
        $this->assertTrue($this->team->hasOutgoingEmailConfig());
    }

    /** @test */
    public function it_gets_outgoing_email_configuration_with_fallbacks()
    {
        // Test with team settings
        $this->team->setSetting('mail_host', 'team-smtp.example.com');
        $this->team->setSetting('mail_port', '465');
        $this->team->setSetting('mail_encryption', 'ssl');
        $this->team->setSetting('mail_username', 'team@example.com');
        $this->team->setSetting('mail_password', 'team_password', ['is_encrypted' => true]);
        $this->team->setSetting('mail_from_name', 'Team Name');
        $this->team->setSetting('mail_from_address', 'noreply@team.com');

        $config = $this->team->getOutgoingEmailConfig();

        $this->assertEquals('team-smtp.example.com', $config['host']);
        $this->assertEquals('465', $config['port']);
        $this->assertEquals('ssl', $config['encryption']);
        $this->assertEquals('team@example.com', $config['username']);
        $this->assertEquals('team_password', $config['password']);
        $this->assertEquals('Team Name', $config['from_name']);
        $this->assertEquals('noreply@team.com', $config['from_address']);

        // Test fallback to .env values
        $this->team->settings()->delete();
        $config = $this->team->getOutgoingEmailConfig();

        $this->assertEquals(env('MAIL_HOST'), $config['host']);
        $this->assertEquals(env('MAIL_PORT', 587), $config['port']);
    }

    /** @test */
    public function it_checks_incoming_email_configuration()
    {
        // Initially no IMAP config
        $this->assertFalse($this->team->hasIncomingEmailConfig());

        // Set partial config
        $this->team->setSetting('imap_host', 'imap.example.com');
        $this->assertFalse($this->team->hasIncomingEmailConfig());

        // Set complete config
        $this->team->setSetting('imap_username', 'user@example.com');
        $this->team->setSetting('imap_password', 'password', ['is_encrypted' => true]);
        $this->assertTrue($this->team->hasIncomingEmailConfig());
    }

    /** @test */
    public function it_gets_incoming_email_configuration()
    {
        $this->team->setSetting('imap_host', 'imap.example.com');
        $this->team->setSetting('imap_port', '993');
        $this->team->setSetting('imap_encryption', 'ssl');
        $this->team->setSetting('imap_username', 'user@example.com');
        $this->team->setSetting('imap_password', 'secret', ['is_encrypted' => true]);

        $config = $this->team->getIncomingEmailConfig();

        $this->assertEquals('imap.example.com', $config['host']);
        $this->assertEquals('993', $config['port']);
        $this->assertEquals('ssl', $config['encryption']);
        $this->assertEquals('user@example.com', $config['username']);
        $this->assertEquals('secret', $config['password']);
    }

    /** @test */
    public function it_checks_stripe_configuration()
    {
        // Initially no Stripe config
        $this->assertFalse($this->team->hasStripeConfig());

        // Set partial config
        $this->team->setSetting('stripe_public', 'pk_test_123');
        $this->assertFalse($this->team->hasStripeConfig());

        // Set complete config
        $this->team->setSetting('stripe_secret', 'sk_test_456', ['is_encrypted' => true]);
        $this->assertTrue($this->team->hasStripeConfig());
    }

    /** @test */
    public function it_gets_stripe_configuration()
    {
        $this->team->setSetting('stripe_public', 'pk_test_123');
        $this->team->setSetting('stripe_secret', 'sk_test_456', ['is_encrypted' => true]);
        $this->team->setSetting('stripe_webhook', 'whsec_789', ['is_encrypted' => true]);

        $this->assertEquals('pk_test_123', $this->team->getSetting('stripe_public'));
        $this->assertEquals('sk_test_456', $this->team->getSetting('stripe_secret'));
        $this->assertEquals('whsec_789', $this->team->getSetting('stripe_webhook'));
    }

    /** @test */
    public function it_validates_setting_data_types()
    {
        // String setting
        $this->team->setSetting('string_setting', 'test', ['type' => 'string']);
        $this->assertIsString($this->team->getSetting('string_setting'));

        // Boolean setting
        $this->team->setSetting('bool_setting', true, ['type' => 'boolean']);
        $this->assertIsBool($this->team->getSetting('bool_setting'));

        // Integer setting
        $this->team->setSetting('int_setting', 123, ['type' => 'integer']);
        $this->assertIsInt($this->team->getSetting('int_setting'));

        // JSON setting
        $this->team->setSetting('json_setting', ['key' => 'value'], ['type' => 'json']);
        $this->assertIsArray($this->team->getSetting('json_setting'));
        $this->assertEquals(['key' => 'value'], $this->team->getSetting('json_setting'));
    }

    /** @test */
    public function it_handles_setting_groups()
    {
        $this->team->setSetting('setting1', 'value1', ['group' => 'group_a']);
        $this->team->setSetting('setting2', 'value2', ['group' => 'group_a']);
        $this->team->setSetting('setting3', 'value3', ['group' => 'group_b']);

        $groupA = $this->team->settings()->where('group', 'group_a')->get();
        $groupB = $this->team->settings()->where('group', 'group_b')->get();

        $this->assertCount(2, $groupA);
        $this->assertCount(1, $groupB);
    }

    /** @test */
    public function it_updates_existing_settings()
    {
        $this->team->setSetting('update_test', 'initial_value');
        $this->assertEquals('initial_value', $this->team->getSetting('update_test'));

        $this->team->setSetting('update_test', 'updated_value');
        $this->assertEquals('updated_value', $this->team->getSetting('update_test'));

        // Should only have one record in database
        $count = TeamSetting::where('team_id', $this->team->id)
            ->where('key', 'update_test')
            ->count();
        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_supports_deprecated_method_names()
    {
        // Set up email config
        $this->team->setSetting('mail_host', 'smtp.test.com');
        $this->team->setSetting('mail_username', 'test@test.com');
        $this->team->setSetting('mail_password', 'password', ['is_encrypted' => true]);

        // Test deprecated methods still work
        $this->assertTrue(method_exists($this->team, 'hasEmailConfig'));
        $this->assertTrue(method_exists($this->team, 'getEmailConfig'));
        $this->assertTrue(method_exists($this->team, 'hasImapConfig'));
        $this->assertTrue(method_exists($this->team, 'getImapConfig'));

        // Verify they return same data
        $this->assertEquals(
            $this->team->getOutgoingEmailConfig(),
            $this->team->getEmailConfig(),
        );
        $this->assertEquals(
            $this->team->hasOutgoingEmailConfig(),
            $this->team->hasEmailConfig(),
        );
    }

    /** @test */
    public function it_handles_empty_and_null_values()
    {
        // Test null value
        $this->team->setSetting('null_test', null);
        $this->assertNull($this->team->getSetting('null_test'));

        // Test empty string
        $this->team->setSetting('empty_test', '');
        $this->assertEquals('', $this->team->getSetting('empty_test'));

        // Test zero
        $this->team->setSetting('zero_test', '0');
        $this->assertEquals('0', $this->team->getSetting('zero_test'));
    }

    /** @test */
    public function it_cascades_delete_settings_when_team_deleted()
    {
        $this->team->setSetting('cascade_test', 'value');

        $settingExists = TeamSetting::where('team_id', $this->team->id)
            ->where('key', 'cascade_test')
            ->exists();
        $this->assertTrue($settingExists);

        $this->team->delete();

        $settingExists = TeamSetting::where('team_id', $this->team->id)
            ->where('key', 'cascade_test')
            ->exists();
        $this->assertFalse($settingExists);
    }

    /** @test */
    public function it_maintains_unique_team_key_constraint()
    {
        $this->team->setSetting('unique_test', 'value1');

        // Should update, not create duplicate
        $this->team->setSetting('unique_test', 'value2');

        $count = TeamSetting::where('team_id', $this->team->id)
            ->where('key', 'unique_test')
            ->count();

        $this->assertEquals(1, $count);
        $this->assertEquals('value2', $this->team->getSetting('unique_test'));
    }
}
