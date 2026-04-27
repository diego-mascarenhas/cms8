<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Models\AgentConversationMessage;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProactiveOutreachSlashTest extends TestCase
{
    use RefreshDatabase;

    public static int $systemOnboardingMediaCalls = 0;

    public function test_web_assistant_accepts_slash_enviar_demo_for_admin(): void
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

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/enviar-demo +34600111222',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action_performed' => 'proactive_whatsapp_outreach',
        ]);
        $this->assertTrue(
            AgentConversationMessage::query()
                ->where('user_id', $user->id)
                ->where('role', 'user')
                ->where('content', '/enviar-demo +34600111222')
                ->exists(),
        );
    }

    public function test_web_assistant_rejects_slash_outreach_for_non_admin(): void
    {
        Config::set('whatsapp.driver', 'twilio');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

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

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/enviar-demo +34600111222',
        ]);

        $response->assertStatus(403);
    }

    public function test_web_assistant_accepts_slash_enviar_onboarding_for_admin(): void
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
            'section_key' => 'onboarding',
            'section_label' => 'Onboarding WhatsApp',
            'prompt_instruction' => 'Send a short onboarding welcome by WhatsApp.',
            'is_active' => true,
            'order' => 1,
        ]);

        AnonymousAgent::fake(['Hola, te guiamos en los primeros pasos.']);

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

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/enviar-onboarding +34600111222',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action_performed' => 'proactive_whatsapp_outreach',
        ]);
        $this->assertTrue(
            AgentConversationMessage::query()
                ->where('user_id', $user->id)
                ->where('role', 'user')
                ->where('content', '/enviar-onboarding +34600111222')
                ->exists(),
        );
    }

    public function test_web_assistant_accepts_system_onboarding_for_admin(): void
    {
        Config::set('whatsapp.driver', 'twilio');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        self::$systemOnboardingMediaCalls = 0;

        $this->app->instance(WhatsAppGateway::class, new class implements WhatsAppGateway
        {
            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                return 'ok';
            }

            public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
            {
                \Tests\Feature\AdminProactiveOutreachSlashTest::$systemOnboardingMediaCalls++;

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

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/system-onboarding +34600111222',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action_performed' => 'system_onboarding_whatsapp',
        ]);
        $this->assertSame(6, self::$systemOnboardingMediaCalls);
    }
}
