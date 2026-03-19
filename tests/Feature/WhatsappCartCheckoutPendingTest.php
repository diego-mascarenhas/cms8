<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\TwilioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class WhatsappCartCheckoutPendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Cache::flush();

        parent::tearDown();
    }

    public function test_affirmative_without_checkout_pending_returns_null_and_does_not_send_whatsapp(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $twilio = Mockery::mock(TwilioService::class, [$team])->makePartial();
        $twilio->shouldNotReceive('sendWhatsApp');

        $result = $twilio->processCartCommands('+573001112223', 'SÍ!');

        $this->assertNull($result);
    }

    public function test_affirmative_with_checkout_pending_and_empty_cart_sends_empty_cart_message(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $phone = '+573001112223';
        $digits = '573001112223';
        Cache::put('whatsapp_checkout_pending:'.$digits, true, now()->addMinutes(45));

        $twilio = Mockery::mock(TwilioService::class, [$team])->makePartial();
        $twilio->shouldReceive('sendWhatsApp')
            ->once()
            ->withArgs(function (string $to, string $message): bool
            {
                return str_contains($message, 'vacío');
            });

        $result = $twilio->processCartCommands($phone, 'si');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
    }

    public function test_resolve_cart_team_id_prefers_webhook_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $service = new TwilioService($team);

        $method = (new ReflectionClass($service))->getMethod('resolveCartTeamId');
        $method->setAccessible(true);

        $this->assertSame((int) $team->id, $method->invoke($service, '+1234567890'));
    }
}
