<?php

namespace Tests\Unit;

use App\Support\MailComposeReplyForward;
use Tests\TestCase;

class MailComposeReplyForwardTest extends TestCase
{
    public function test_reply_payload_uses_sender_for_inbox_messages(): void
    {
        app()->setLocale('es_ES');

        $payload = MailComposeReplyForward::replyPayload([
            'from' => 'Javier Castro <javier@cliente.com>',
            'to' => 'me@team.com',
            'subject' => 'Presupuesto Q2',
            'date_display' => '17 jun. 2026, 17:58',
            'body' => 'Hola, adjunto presupuesto.',
        ], 'inbox');

        $this->assertSame(['javier@cliente.com'], $payload['recipients']);
        $this->assertSame('Re: Presupuesto Q2', $payload['subject']);
        $this->assertStringContainsString('---------- Mensaje original ----------', $payload['body']);
        $this->assertStringContainsString('Hola, adjunto presupuesto.', $payload['body']);
    }

    public function test_reply_payload_uses_recipient_for_sent_messages(): void
    {
        $payload = MailComposeReplyForward::replyPayload([
            'from' => 'me@team.com',
            'to' => 'Cliente <cliente@example.com>',
            'subject' => 'Re: Propuesta',
            'date_display' => '17 jun. 2026, 17:58',
            'body' => 'Gracias.',
        ], 'sent');

        $this->assertSame(['cliente@example.com'], $payload['recipients']);
        $this->assertSame('Re: Propuesta', $payload['subject']);
    }

    public function test_forward_payload_prefixes_subject_and_quotes_message(): void
    {
        app()->setLocale('es_ES');

        $payload = MailComposeReplyForward::forwardPayload([
            'from' => 'Partner <partner@example.com>',
            'subject' => 'Informe',
            'date_display' => '16 jun. 2026, 10:00',
            'body' => '<p>Contenido HTML</p>',
        ]);

        $this->assertSame([], $payload['recipients']);
        $this->assertSame('Fwd: Informe', $payload['subject']);
        $this->assertStringContainsString('---------- Mensaje reenviado ----------', $payload['body']);
        $this->assertStringContainsString('Contenido HTML', $payload['body']);
    }
}
