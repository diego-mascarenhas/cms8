<?php

namespace Tests\Feature;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Conversation;
use App\Models\DocumentIngestion;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantActivityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_team_assistant_activity_page(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam ?? $admin->ownedTeams()->first();
        $admin->forceFill(['current_team_id' => $team->id])->save();
        $admin->assignRole('admin');
        $team->setSetting('whatsapp_from', '34600000001');

        $conversation = AgentConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'team_id' => $team->id,
            'title' => 'Billing follow-up',
        ]);

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $admin->id,
            'agent' => 'chat_assistant',
            'role' => 'assistant',
            'content' => 'Test response',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [
                'prompt_tokens' => 1200,
                'completion_tokens' => 300,
                'total_tokens' => 1500,
            ],
            'meta' => [],
        ]);

        $response = $this->actingAs($admin)->get(route('assistant.activity'));

        $response->assertOk();
        $response->assertSee('Actividad de IA');

        $dataResponse = $this->actingAs($admin)->getJson(route('assistant.activity.data', [
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $dataResponse->assertOk();
        $dataResponse->assertJsonFragment([
            'conversation_title' => 'Billing follow-up',
            'total_tokens_value' => 1500,
        ]);
    }

    public function test_non_admin_cannot_view_team_assistant_activity_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->get(route('assistant.activity'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_team_document_ingestions_page(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam ?? $admin->ownedTeams()->first();
        $admin->forceFill(['current_team_id' => $team->id])->save();
        $admin->assignRole('admin');

        $source = Source::query()->create([
            'name' => 'WhatsApp',
            'base_url' => 'https://wa.me/',
            'icon' => 'fa-whatsapp',
            'color' => '#25D366',
        ]);

        $primaryIngestion = DocumentIngestion::query()->create([
            'team_id' => $team->id,
            'source_id' => $source->id,
            'source_reference' => 'msg_123',
            'file_name' => 'factura-test.pdf',
            'file_url' => 'https://cdn.example.com/factura-test.pdf',
            'mime_type' => 'application/pdf',
            'document_type' => 'invoice',
            'classification_status' => 'classified',
            'classification_confidence' => 0.85,
        ]);

        $conversation = Conversation::query()->create([
            'message_sid' => 'msg-fallback-null-team',
            'channel' => 'whatsapp',
            'from' => '34600000099',
            'to' => 'whatsapp:34600000001',
            'body' => 'fallback ingestion',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        DocumentIngestion::query()->create([
            'team_id' => null,
            'source_id' => $source->id,
            'conversation_id' => $conversation->id,
            'source_reference' => 'msg-fallback-null-team',
            'file_name' => 'fallback.pdf',
            'file_url' => 'https://cdn.example.com/fallback.pdf',
            'mime_type' => 'application/pdf',
            'document_type' => 'unknown',
            'classification_status' => 'failed',
            'classification_confidence' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('assistant.documents'));
        $response->assertOk();
        $response->assertSee('Documentos procesados');

        $dataResponse = $this->actingAs($admin)->getJson(route('assistant.documents.data', [
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $dataResponse->assertOk();
        $dataResponse->assertJsonFragment([
            'document_type' => 'invoice',
            'source_name' => 'WhatsApp',
            'reception_note' => 'URL recibida correctamente',
        ]);
        $dataResponse->assertJsonFragment([
            'source_reference' => 'msg-fallback-null-team',
            'classification_status' => 'failed',
        ]);

        $detailResponse = $this->actingAs($admin)->get(route('assistant.documents.show', $primaryIngestion->id));
        $detailResponse->assertOk();
        $detailResponse->assertSee('Detalle de interpretación');
    }
}
