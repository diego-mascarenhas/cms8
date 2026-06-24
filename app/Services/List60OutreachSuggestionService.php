<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Prompt;
use App\Models\User;
use App\Support\List60OutreachPromptDefaults;

class List60OutreachSuggestionService
{
    public const ROUTING_KEY_FIRST_CONTACT = 'list60:primer_contacto';

    public const ROUTING_KEY_FOLLOW_UP = 'list60:seguimiento';

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
        ?string $list60StatusName = null,
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

        $instruction = $this->buildInstruction($teamId, $channel, $contact, $notes, $sentiment, $categories, $list60StatusName);

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
        int $teamId,
        string $channel,
        Contact $contact,
        string $notes,
        ?array $sentiment,
        array $categories,
        ?string $list60StatusName = null,
    ): string {
        DefaultAssistantFlowPromptsService::syncForTeam($teamId);

        $routingKey = $this->routingKeyForStatus($list60StatusName);
        $prompt = Prompt::findByRoutingKey($routingKey, $teamId);

        $parts = [
            $this->resolveObjectiveInstruction($prompt, $list60StatusName),
            '',
            'Contexto del contacto:',
            '- Nombre del contacto: '.$contact->name,
        ];

        if ($list60StatusName !== null && $list60StatusName !== '')
        {
            $parts[] = '- Estado en Lista de 60: '.$list60StatusName;
        }

        if ($categories !== [])
        {
            $parts[] = '- Categorías: '.implode(', ', $categories);
        }

        if ($sentiment)
        {
            $sentimentLine = '- Estado emocional: '.$sentiment['name'];
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
            $parts[] = '- Notas del CRM: '.trim($notes);
        }

        $parts[] = '';
        $parts[] = 'Idioma: español, salvo que el contexto indique otro idioma.';

        if ($channel === 'email')
        {
            $parts[] = 'Canal: email en texto plano.';
            $parts[] = 'Responde solo con un objeto JSON (sin markdown ni comentarios). Claves: "subject" (asunto breve y directo) y "body" (cuerpo del email: saludo, párrafos cortos, cierre).';
        } else
        {
            $parts[] = 'Canal: WhatsApp (texto plano, conversacional, máximo 4 párrafos cortos, sin HTML).';
            $parts[] = 'Responde solo con un objeto JSON (sin markdown ni comentarios). Clave: "message" (texto del WhatsApp).';
        }

        return implode("\n", $parts);
    }

    public function routingKeyForStatus(?string $list60StatusName): string
    {
        if ($list60StatusName === 'Sin contactar' || $list60StatusName === null || $list60StatusName === '')
        {
            return self::ROUTING_KEY_FIRST_CONTACT;
        }

        return self::ROUTING_KEY_FOLLOW_UP;
    }

    private function resolveObjectiveInstruction(?Prompt $prompt, ?string $list60StatusName): string
    {
        if ($prompt !== null && $prompt->is_active)
        {
            $instruction = trim((string) $prompt->prompt_instruction);
            if ($instruction !== '')
            {
                return $instruction;
            }
        }

        return $this->defaultObjectiveInstruction($list60StatusName);
    }

    private function defaultObjectiveInstruction(?string $list60StatusName): string
    {
        if ($list60StatusName === 'Sin contactar' || $list60StatusName === null || $list60StatusName === '')
        {
            return List60OutreachPromptDefaults::firstContactInstruction();
        }

        return List60OutreachPromptDefaults::followUpInstruction();
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
