<?php

namespace Tests\Unit;

use App\Models\Prospect;
use App\Models\Team;
use App\Models\User;
use App\Services\SystemOnboardingWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemOnboardingWhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_try_handle_inbound_advances_to_next_step_on_listo(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        Prospect::query()->create([
            'channel' => Prospect::CHANNEL_WHATSAPP,
            'external_id' => '34600111222',
            'team_id' => $team->id,
            'onboarding_step' => 'system_onboarding_1',
            'data' => [
                'system_onboarding' => [
                    'active' => true,
                    'step' => 1,
                    'awaiting_rep_message' => false,
                ],
            ],
        ]);

        $reply = app(SystemOnboardingWhatsAppService::class)->tryHandleInbound($team, '34600111222', 'listo');

        $this->assertNotNull($reply);
        $this->assertSame('/images/system-onboarding/step-2.png', $reply['media_path']);
        $this->assertStringContainsString('Paso 2/6', $reply['message']);
    }

    public function test_try_handle_inbound_captures_representative_message(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $prospect = Prospect::query()->create([
            'channel' => Prospect::CHANNEL_WHATSAPP,
            'external_id' => '34600111222',
            'team_id' => $team->id,
            'onboarding_step' => 'system_onboarding_2',
            'data' => [
                'system_onboarding' => [
                    'active' => true,
                    'step' => 2,
                    'awaiting_rep_message' => true,
                ],
            ],
        ]);

        $reply = app(SystemOnboardingWhatsAppService::class)->tryHandleInbound(
            $team,
            '34600111222',
            'Necesito hablar hoy por la tarde',
        );

        $this->assertNotNull($reply);
        $this->assertStringContainsString('representante', mb_strtolower($reply['message']));

        $data = $prospect->fresh()->data;
        $this->assertFalse((bool) ($data['system_onboarding']['active'] ?? true));
        $this->assertNotEmpty($data['representative_requests'] ?? []);
    }
}
