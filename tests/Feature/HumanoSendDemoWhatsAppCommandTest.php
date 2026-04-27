<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Models\AgentConversationMessage;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HumanoSendDemoWhatsAppCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fails_without_team_option(): void
    {
        $exit = Artisan::call('humano:send-demo', ['phone' => '+34600111222']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--team', Artisan::output());
    }

    public function test_command_fails_when_user_is_not_admin(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $exit = Artisan::call('humano:send-demo', [
            'phone' => '+34600111222',
            '--team' => (string) $team->id,
            '--user' => (string) $user->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Only admin', Artisan::output());
    }

    public function test_command_succeeds_for_admin_with_prompt_and_fake_gateway(): void
    {
        Config::set('whatsapp.driver', 'twilio');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $mod = Module::query()->create([
            'name' => 'Chat',
            'key' => 'chat',
            'is_core' => false,
            'status' => 1,
        ]);
        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $mod->id,
            'section_key' => 'demo',
            'section_label' => 'Demo onboarding',
            'prompt_instruction' => 'Send a short onboarding welcome.',
            'is_active' => true,
            'order' => 1,
        ]);

        AnonymousAgent::fake(['Hola, bienvenido al demo.']);

        $this->app->instance(WhatsAppGateway::class, new class implements WhatsAppGateway
        {
            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                return 'ok';
            }

            public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
            {
                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function getQrUrl(): ?string
            {
                return null;
            }

            public function getConnectionStatus(): ?array
            {
                return ['status' => 'connected'];
            }
        });

        $exit = Artisan::call('humano:send-demo', [
            'phone' => '+34600111222',
            '--team' => (string) $team->id,
            '--user' => (string) $user->id,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertTrue(
            AgentConversationMessage::query()
                ->where('user_id', $user->id)
                ->where('role', 'user')
                ->where('content', 'like', 'humano:send-demo%')
                ->exists(),
            'Expected CLI outreach user message persisted to agent history.',
        );
    }
}
