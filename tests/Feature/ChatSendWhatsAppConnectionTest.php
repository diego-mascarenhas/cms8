<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatSendWhatsAppConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    private function userWithTeam(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    public function test_chat_send_returns_not_connected_when_local_whatsapp_is_disconnected(): void
    {
        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);

        Http::fake([
            'http://127.0.0.1:3000/status*' => Http::response(['status' => 'disconnected', 'number' => null], 200),
        ]);

        $user = $this->userWithTeam();
        $team = $user->currentTeam;
        $this->assertInstanceOf(Team::class, $team);
        $team->setSetting('whatsapp_from', '5491111223344');

        $response = $this->actingAs($user)->postJson(route('chat.send'), [
            'to' => '5491199988877',
            'message' => 'Hola desde Humano',
            'use_ai' => false,
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error', __('whatsapp.send.error.not_connected'));
    }
}
