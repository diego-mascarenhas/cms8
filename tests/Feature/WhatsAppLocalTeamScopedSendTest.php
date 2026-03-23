<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppLocalTeamScopedSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_send_whatsapp_includes_team_id_for_team_bounded_service(): void
    {
        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://baileys.test']);
        config(['whatsapp.local.webhook_secret' => '']);

        Http::fake([
            'http://baileys.test/*' => Http::response(['id' => 'msg-1', 'success' => true], 200),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '5491111223344');
        $team->setSetting('whatsapp_service_url', 'http://baileys.test');

        $service = new WhatsAppMessageService($team);
        $service->sendWhatsApp('5491199988877', 'Hola');

        Http::assertSent(function ($request) use ($team): bool
        {
            if (! str_contains($request->url(), '/send-message'))
            {
                return false;
            }
            $data = $request->data();

            return (int) ($data['team_id'] ?? 0) === $team->id
                && ($data['to'] ?? '') === '5491199988877'
                && ($data['body'] ?? '') === 'Hola';
        });
    }

    public function test_assistant_send_whatsapp_tool_uses_team_scoped_local_gateway(): void
    {
        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://baileys.test']);
        config(['whatsapp.local.webhook_secret' => '']);

        Http::fake([
            'http://baileys.test/*' => Http::response(['id' => 'msg-2', 'success' => true], 200),
        ]);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->assignRole($role);
        $team->setSetting('whatsapp_service_url', 'http://baileys.test');

        $service = new AssistantToolsService;
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $team->id, '5491199988877');

        $result = $service->execute('send_whatsapp_message', [
            'phone' => '5491199988877',
            'message' => 'From tools',
        ]);

        $this->assertStringContainsString('WhatsApp message sent', $result);

        Http::assertSent(function ($request) use ($team): bool
        {
            if (! str_contains($request->url(), '/send-message'))
            {
                return false;
            }
            $data = $request->data();

            return (int) ($data['team_id'] ?? 0) === $team->id
                && ($data['to'] ?? '') === '5491199988877';
        });
    }
}
