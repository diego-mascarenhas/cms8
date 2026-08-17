<?php

namespace Tests\Unit;

use App\Models\Conversation;
use Tests\TestCase;

class ConversationIsTranscribedAudioTest extends TestCase
{
    public function test_detects_transcribed_audio_from_metadata(): void
    {
        $conversation = new Conversation;
        $conversation->body = 'Hola.';
        $conversation->metadata = ['TranscribedAudio' => '1'];

        $this->assertTrue($conversation->isTranscribedAudio());
    }

    public function test_detects_transcribed_audio_from_body_prefix(): void
    {
        $conversation = new Conversation;
        $conversation->body = '[Audio]: Hola.';

        $this->assertTrue($conversation->isTranscribedAudio());
    }

    public function test_detects_transcribed_audio_from_media_type(): void
    {
        $conversation = new Conversation;
        $conversation->body = 'Hola.';
        $conversation->media = [['url' => 'https://example.test/a.ogg', 'content_type' => 'audio/ogg']];

        $this->assertTrue($conversation->isTranscribedAudio());
    }

    public function test_plain_text_is_not_transcribed_audio(): void
    {
        $conversation = new Conversation;
        $conversation->body = 'Hola.';

        $this->assertFalse($conversation->isTranscribedAudio());
    }
}
