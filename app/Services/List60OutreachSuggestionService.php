<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;

class List60OutreachSuggestionService
{
    public function __construct(
        private readonly UserResolverService $userResolver,
        private readonly AgentConversationContextService $contextService,
        private readonly ChatAssistantReplyService $replyService,
    ) {}

    /**
     * @param  array{name: string, emoji: string, notes: ?string}|null  $sentiment
     * @param  list<string>  $categories
     * @return array{success: bool, message?: string, subject?: string, body?: string}
     */
    public function suggest(
        User $user,
        Contact $contact,
        string $channel,
        string $notes,
        ?array $sentiment,
        array $categories,
    ): array {
        $team = $user->currentTeam;
        if (! $team)
        {
            return ['success' => false, 'message' => __('No hay equipo seleccionado.')];
        }

        $teamId = (int) $team->id;
        $contextUser = $this->userResolver->resolveUserForConversation(null, (int) $contact->id) ?? $user;

        $customerPhone = null;
        if ($contact->phone)
        {
            $customerPhone = preg_replace('/[^0-9]/', '', (string) $contact->phone);
            if ($customerPhone === '')
            {
                $customerPhone = null;
            }
        }

        $history = $this->contextService->getHistoryForPrompt(
            $contextUser->id,
            AgentConversationContextService::DEFAULT_HISTORY_LIMIT,
        );

        $instruction = $this->buildInstruction($channel, $contact, $notes, $sentiment, $categories);

        $replyResponse = $this->replyService->getReply(
            $instruction,
            $history,
            $teamId,
            true,
            $contextUser->id,
            $customerPhone,
            null,
            (int) $contact->id,
            true,
        );

        if (! $replyResponse['success'])
        {
            return [
                'success' => false,
                'message' => $replyResponse['message'] ?? __('Error'),
            ];
        }

        $text = trim((string) ($replyResponse['text'] ?? ''));
        if ($text === '')
        {
            return ['success' => false, 'message' => __('Error')];
        }

        if ($channel === 'email')
        {
            $parsed = $this->parseEmailSuggestion($text);

            return [
                'success' => true,
                'subject' => $parsed['subject'],
                'body' => $parsed['body'],
                'message' => $parsed['body'],
            ];
        }

        return [
            'success' => true,
            'message' => $this->parseWhatsAppSuggestion($text),
        ];
    }

    /**
     * @param  array{name: string, emoji: string, notes: ?string}|null  $sentiment
     * @param  list<string>  $categories
     */
    private function buildInstruction(
        string $channel,
        Contact $contact,
        string $notes,
        ?array $sentiment,
        array $categories,
    ): string {
        $parts = [
            'You are helping a sales operator follow up with a CRM contact from the Lista de 60 outreach screen.',
            'Contact name: '.$contact->name,
        ];

        if ($categories !== [])
        {
            $parts[] = 'Contact categories: '.implode(', ', $categories);
        }

        if ($sentiment)
        {
            $sentimentLine = 'Current emotional state: '.$sentiment['name'];
            if (! empty($sentiment['emoji']))
            {
                $sentimentLine .= ' '.$sentiment['emoji'];
            }
            if (! empty($sentiment['notes']))
            {
                $sentimentLine .= ' — '.$sentiment['notes'];
            }
            $parts[] = $sentimentLine;
        }

        if (trim($notes) !== '')
        {
            $parts[] = 'CRM notes about this contact: '.trim($notes);
        }

        if ($channel === 'email')
        {
            $parts[] = 'Draft a follow-up email. Reply with a single JSON object only (no markdown fences, no commentary). Keys: "subject" (short subject line) and "body" (plain text email: greeting, short paragraphs, closing).';
            $parts[] = 'Use a clear professional tone. Prefer Spanish unless notes/context imply another language.';
        } else
        {
            $parts[] = 'Draft a short WhatsApp follow-up message (plain text, conversational, 1-4 short paragraphs max, no HTML).';
            $parts[] = 'Reply with a single JSON object only (no markdown fences, no commentary). Key: "message" (the WhatsApp text).';
            $parts[] = 'Prefer Spanish unless notes/context imply another language.';
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function parseEmailSuggestion(string $text): array
    {
        $raw = trim($text);
        if ($raw === '')
        {
            return ['subject' => '', 'body' => ''];
        }

        $decoded = $this->decodeJsonPayload($raw);
        if (is_array($decoded))
        {
            $subject = isset($decoded['subject']) && is_string($decoded['subject']) ? trim($decoded['subject']) : '';
            $body = isset($decoded['body']) && is_string($decoded['body']) ? trim($decoded['body']) : '';

            if ($subject !== '' || $body !== '')
            {
                return ['subject' => $subject, 'body' => $body];
            }
        }

        return ['subject' => '', 'body' => $raw];
    }

    private function parseWhatsAppSuggestion(string $text): string
    {
        $decoded = $this->decodeJsonPayload(trim($text));
        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message']))
        {
            $message = trim($decoded['message']);
            if ($message !== '')
            {
                return $message;
            }
        }

        return trim($text);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(string $raw): ?array
    {
        if ($raw === '')
        {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded))
        {
            return $decoded;
        }

        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/m', $raw, $matches))
        {
            $decoded = json_decode(trim($matches[1]), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
