<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Support\WhatsAppDriver;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamWhatsAppDriverTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTeam(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_whatsapp_status_defaults_to_local_when_global_config_is_twilio(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Config::set('whatsapp.driver', 'twilio');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'disconnected'], 200),
        ]);

        $user = $this->userWithTeam();

        $this->actingAs($user)
            ->getJson(route('chat.whatsapp-status'))
            ->assertOk()
            ->assertJson([
                'driver' => WhatsAppDriver::LOCAL,
            ]);
    }

    public function test_whatsapp_status_uses_team_twilio_driver(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake();

        $user = $this->userWithTeam();
        $user->currentTeam->setSetting(WhatsAppDriver::SETTING_KEY, WhatsAppDriver::TWILIO, ['group' => 'chat']);

        $this->actingAs($user)
            ->getJson(route('chat.whatsapp-status'))
            ->assertOk()
            ->assertJson([
                'driver' => WhatsAppDriver::TWILIO,
                'status' => 'disconnected',
                'isTeamConnected' => false,
            ]);

        Http::assertNothingSent();
    }

    public function test_whatsapp_qr_image_returns_204_when_team_uses_twilio(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake();

        $user = $this->userWithTeam();
        $user->currentTeam->setSetting(WhatsAppDriver::SETTING_KEY, WhatsAppDriver::TWILIO, ['group' => 'chat']);

        $this->actingAs($user)
            ->get(route('chat.whatsapp-qr-image'))
            ->assertNoContent();

        Http::assertNothingSent();
    }

    public function test_chat_settings_persist_whatsapp_driver(): void
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = $this->userWithTeam();
        $user->assignRole('admin');
        $team = $user->currentTeam;

        $this->actingAs($user)->put(route('team-settings.update', $team), [
            'chat' => [
                'whatsapp_driver' => WhatsAppDriver::TWILIO,
                'assistant_auto_respond' => '1',
                'assistant_chat_stub' => '0',
                'assistant_keyword_intent_routing' => '0',
                'chat_ai_assistance_blocked' => '0',
            ],
        ])->assertRedirect();

        $this->assertSame(WhatsAppDriver::TWILIO, $team->fresh()->getWhatsAppDriver());
        $this->assertFalse($team->fresh()->usesLocalWhatsApp());
    }

    public function test_chat_settings_persist_meta_cloud_api_driver(): void
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = $this->userWithTeam();
        $user->assignRole('admin');
        $team = $user->currentTeam;

        $this->actingAs($user)->put(route('team-settings.update', $team), [
            'chat' => [
                'whatsapp_driver' => WhatsAppDriver::META,
                'assistant_auto_respond' => '1',
                'assistant_chat_stub' => '0',
                'assistant_keyword_intent_routing' => '0',
                'chat_ai_assistance_blocked' => '0',
            ],
        ])->assertRedirect();

        $this->assertSame(WhatsAppDriver::META, $team->fresh()->getWhatsAppDriver());
        $this->assertFalse($team->fresh()->usesLocalWhatsApp());
    }

    public function test_whatsapp_status_stays_ok_when_local_service_is_unreachable(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://127.0.0.1:9');

        Http::fake(function ()
        {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 7');
        });

        $this->actingAs($this->userWithTeam())
            ->getJson(route('chat.whatsapp-status'))
            ->assertOk()
            ->assertJsonPath('status', 'unreachable')
            ->assertJsonPath('isTeamConnected', false);
    }

    public function test_whatsapp_status_and_qr_treat_meta_as_official_not_baileys(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake();

        $user = $this->userWithTeam();
        $user->currentTeam->setSetting(WhatsAppDriver::SETTING_KEY, WhatsAppDriver::META, ['group' => 'chat']);

        $this->actingAs($user)
            ->getJson(route('chat.whatsapp-status'))
            ->assertOk()
            ->assertJson([
                'driver' => WhatsAppDriver::META,
                'status' => 'disconnected',
                'isTeamConnected' => false,
            ]);

        $this->actingAs($user)
            ->get(route('chat.whatsapp-qr-image'))
            ->assertNoContent();

        Http::assertNothingSent();
    }
}
