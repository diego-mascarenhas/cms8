<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Email;
use App\Models\Mailbox;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\PerformanceDigestUnreadMessageDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceDigestUnreadMessageDetailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_unread_returns_one_detail_per_message(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('whatsapp_from', '34999000111');

        Module::firstOrCreate(['key' => 'chat'], ['name' => 'Chat', 'is_core' => false]);
        $team->enableModule('chat');
        $team = $team->fresh();

        Conversation::create([
            'message_sid' => 'SM_digest_detail_1',
            'channel' => 'whatsapp',
            'from' => '34600111222',
            'to' => '34999000111',
            'body' => '¿Cuánto cuesta el servicio?',
            'status' => 'received',
            'direction' => 'inbound',
        ]);
        Conversation::create([
            'message_sid' => 'SM_digest_detail_2',
            'channel' => 'whatsapp',
            'from' => '34600333444',
            'to' => '34999000111',
            'body' => 'Gracias por la info',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $details = app(PerformanceDigestUnreadMessageDetailService::class)->forHighlightKey('whatsapp_unread', $team);

        $this->assertCount(2, $details);
        $this->assertSame('whatsapp', $details[0]['channel']);
        $this->assertStringContainsString('Cuánto cuesta', $details[0]['preview']);
        $this->assertStringNotContainsString('sobre', mb_strtolower($details[0]['suggestion']));
        $this->assertStringNotContainsString('«', $details[0]['suggestion']);
        $this->assertMatchesRegularExpression('/^(Hola|Buenos días|Hi|Good morning)\b/u', $details[0]['suggestion']);
        $this->assertNotSame('', $details[0]['response_hint']);
    }

    public function test_email_unread_returns_one_detail_per_email(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        Module::firstOrCreate(['key' => 'mailbox'], ['name' => 'Mailbox', 'is_core' => false]);
        $team->enableModule('mailbox');
        $team = $team->fresh();

        $mailbox = Mailbox::factory()->create(['team_id' => $team->id]);

        Email::factory()->create([
            'mailbox_id' => $mailbox->id,
            'team_id' => $team->id,
            'from_address' => 'Ana García <ana@example.com>',
            'subject' => 'Presupuesto servicio',
            'body_text' => '¿Podemos agendar una llamada mañana?',
            'seen' => false,
        ]);
        Email::factory()->create([
            'mailbox_id' => $mailbox->id,
            'team_id' => $team->id,
            'from_address' => 'juan@example.com',
            'subject' => 'Gracias',
            'body_text' => 'Gracias por la información',
            'seen' => false,
        ]);

        $details = app(PerformanceDigestUnreadMessageDetailService::class)->forHighlightKey('email_unread', $team);

        $this->assertCount(2, $details);
        $this->assertSame('email', $details[0]['channel']);
        $this->assertStringContainsString('Presupuesto servicio', $details[0]['preview']);
        $this->assertStringContainsString('Hola Ana', $details[0]['suggestion']);
        $this->assertStringNotContainsString('«', $details[0]['suggestion']);
        $this->assertStringContainsString('compose=1', $details[0]['action_url'] ?? '');
        $this->assertStringContainsString('ana%40example.com', $details[0]['action_url'] ?? '');
    }
}
