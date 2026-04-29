<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DocumentIngestion;
use App\Models\Team;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatsAppWebhookIngressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);

        if (DB::table('contact_statuses')->where('id', 1)->doesntExist())
        {
            DB::table('contact_statuses')->insert([
                'id' => 1,
                'name' => 'Lead',
                'label_class' => 'bg-label-success',
            ]);
        }
    }

    public function test_twilio_webhook_media_ingestion_detects_media_url_without_num_media(): void
    {
        Config::set('whatsapp.driver', 'twilio');
        Config::set('services.twilio.sid', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        Config::set('services.twilio.token', 'test-token');
        Config::set('services.twilio.whatsapp_from', '14155238886');

        Team::factory()->create();

        $response = $this->post(route('whatsapp.webhook'), [
            'MessageSid' => 'SM_media_team_resolution_1',
            'From' => 'whatsapp:+34600000099',
            'To' => 'whatsapp:+34600000001',
            'Body' => 'Adjunto factura',
            'MediaUrl0' => 'https://cdn.example.com/factura-2026.pdf',
            'MediaContentType0' => 'application/pdf',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'document_ingestion' => true,
            'auto_ai_skipped' => 'document_ingestion_pending',
        ]);

        $conversation = Conversation::query()->where('message_sid', 'SM_media_team_resolution_1')->first();
        $this->assertNotNull($conversation);

        $this->assertDatabaseHas('document_ingestions', [
            'conversation_id' => $conversation->id,
            'document_type' => 'invoice',
        ]);

        $this->assertGreaterThan(0, DocumentIngestion::query()->count());
    }
}
