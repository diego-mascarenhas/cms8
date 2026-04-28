<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DocumentIngestion;
use App\Models\Team;
use App\Services\DocumentIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_creates_ingestions_for_whatsapp_media(): void
    {
        $team = Team::factory()->create();
        $conversation = Conversation::create([
            'message_sid' => 'wa-msg-1',
            'channel' => 'whatsapp',
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'files',
            'status' => 'received',
            'direction' => 'inbound',
            'media' => [
                [
                    'url' => 'https://cdn.example.com/factura-abril.pdf',
                    'content_type' => 'application/pdf',
                ],
                [
                    'url' => 'https://cdn.example.com/business-card.jpg',
                    'content_type' => 'image/jpeg',
                ],
            ],
        ]);

        $records = app(DocumentIngestionService::class)->ingestFromConversationMedia(
            $conversation,
            'WhatsApp',
            'wa-msg-1',
            $team->id,
        );

        $this->assertCount(2, $records);
        $this->assertDatabaseCount('document_ingestions', 2);
        $this->assertDatabaseHas('document_ingestions', [
            'conversation_id' => $conversation->id,
            'document_type' => 'invoice',
            'classification_status' => 'classified',
        ]);
        $this->assertDatabaseHas('document_ingestions', [
            'conversation_id' => $conversation->id,
            'document_type' => 'business_card',
            'classification_status' => 'classified',
        ]);
    }

    /**
     * @test
     */
    public function it_marks_unknown_documents_for_manual_review(): void
    {
        $conversation = Conversation::create([
            'message_sid' => 'wa-msg-2',
            'channel' => 'whatsapp',
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'image',
            'status' => 'received',
            'direction' => 'inbound',
            'media' => [
                [
                    'url' => 'https://cdn.example.com/random-image.png',
                    'content_type' => 'image/png',
                ],
            ],
        ]);

        app(DocumentIngestionService::class)->ingestFromConversationMedia($conversation, 'WhatsApp', 'wa-msg-2', null);

        $record = DocumentIngestion::query()->first();
        $this->assertNotNull($record);
        $this->assertSame('unknown', $record->document_type);
        $this->assertSame('needs_review', $record->classification_status);
    }

    /**
     * @test
     */
    public function it_stores_attachment_even_without_url_when_media_entry_exists(): void
    {
        $conversation = Conversation::create([
            'message_sid' => 'wa-msg-3',
            'channel' => 'whatsapp',
            'from' => '34600000000',
            'to' => '34600000001',
            'body' => 'attachment without direct url',
            'status' => 'received',
            'direction' => 'inbound',
            'media' => [
                [
                    'url' => '',
                    'content_type' => 'application/octet-stream',
                ],
            ],
        ]);

        $records = app(DocumentIngestionService::class)->ingestFromConversationMedia($conversation, 'WhatsApp', 'wa-msg-3', null);

        $this->assertCount(1, $records);
        $this->assertDatabaseHas('document_ingestions', [
            'conversation_id' => $conversation->id,
            'source_reference' => 'wa-msg-3',
            'classification_status' => 'needs_review',
        ]);
    }
}
