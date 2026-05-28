<?php

namespace App\Services\Assistant;

use App\Models\User;
use App\Services\AssistantToolsService;
use App\Support\AssistantContactCreationResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * When the LLM replies without calling create_contact, apply clear "nuevo contacto" intent server-side
 * (web assistant + WhatsApp share the same {@see AssistantToolsService} path).
 */
class AssistantInboundContactCreationService
{
    public function __construct(
        protected AssistantToolsService $assistantTools,
    ) {}

    /**
     * @param  array<int, mixed>  $existingToolResults
     * @return array{tool_result: string, contact_id: int, whatsapp_reply: string}|null
     */
    public function tryApplyFromUserMessage(
        User $user,
        int $teamId,
        string $message,
        array $existingToolResults,
    ): ?array {
        Log::info('AssistantInboundContactCreationService start', [
            'user_id' => $user->id,
            'team_id' => $teamId,
            'message_preview' => mb_substr(trim($message), 0, 180),
            'existing_tool_results_count' => count($existingToolResults),
        ]);

        if ($this->assistantTools->wasToolExecuted('create_contact'))
        {
            Log::info('AssistantInboundContactCreationService skipped because create_contact already executed', [
                'user_id' => $user->id,
                'team_id' => $teamId,
            ]);

            return null;
        }

        if (AssistantContactCreationResult::extractFromToolResults($existingToolResults) !== null)
        {
            Log::info('AssistantInboundContactCreationService skipped because creation result already present in tool results', [
                'user_id' => $user->id,
                'team_id' => $teamId,
            ]);

            return null;
        }

        $parsed = $this->parseContactCreationIntent($message);
        if ($parsed === null)
        {
            Log::info('AssistantInboundContactCreationService no contact intent detected', [
                'user_id' => $user->id,
                'team_id' => $teamId,
            ]);

            return null;
        }

        Log::info('AssistantInboundContactCreationService intent detected', [
            'user_id' => $user->id,
            'team_id' => $teamId,
            'parsed' => $parsed,
        ]);

        $this->assistantTools->setRequestContext($user->id, $teamId, null);
        $toolResult = $this->assistantTools->execute('create_contact', $parsed);

        Log::info('AssistantInboundContactCreationService create_contact executed', [
            'user_id' => $user->id,
            'team_id' => $teamId,
            'tool_result_preview' => mb_substr($toolResult, 0, 250),
        ]);

        if (
            str_contains($toolResult, 'not found')
            || str_contains($toolResult, 'permission')
            || str_contains($toolResult, 'required')
            || str_contains($toolResult, 'Error:')
        ) {
            Log::info('AssistantInboundContactCreationService create_contact result rejected by guardrails', [
                'user_id' => $user->id,
                'team_id' => $teamId,
                'tool_result' => $toolResult,
            ]);

            return null;
        }

        $creation = AssistantContactCreationResult::parseToolResultText($toolResult);
        if ($creation === null)
        {
            Log::info('AssistantInboundContactCreationService could not parse contact creation result', [
                'user_id' => $user->id,
                'team_id' => $teamId,
                'tool_result' => $toolResult,
            ]);

            return null;
        }

        Log::info('AssistantInboundContactCreationService finished successfully', [
            'user_id' => $user->id,
            'team_id' => $teamId,
            'contact_id' => $creation['contact_id'],
            'already_exists' => $creation['already_exists'],
        ]);

        return [
            'tool_result' => $toolResult,
            'contact_id' => $creation['contact_id'],
            'whatsapp_reply' => $this->formatWhatsAppReply($parsed, $toolResult, $creation),
        ];
    }

    /**
     * @return array{name: string, email?: string, phone?: string, category_name?: string, notes?: string, birthday?: string}|null
     */
    public function parseContactCreationIntent(string $message): ?array
    {
        $normalized = trim($message);
        if ($normalized === '')
        {
            return null;
        }

        if (preg_match('/nuevo\s*\.?\s*contacto\s*:\s*(.+)$/iu', $normalized, $matches))
        {
            return $this->parseContactPayload(trim($matches[1]));
        }

        if (preg_match('/@/u', $normalized) && preg_match('/categor[ií]a\s+/iu', $normalized))
        {
            return $this->parseContactPayload($normalized);
        }

        return null;
    }

    /**
     * @return array{name: string, email?: string, phone?: string, category_name?: string, notes?: string, birthday?: string}|null
     */
    private function parseContactPayload(string $payload): ?array
    {
        $payload = trim($payload);
        if ($payload === '')
        {
            return null;
        }

        $quotedName = null;
        if (preg_match('/["“”](?<name>[^"“”]{2,})["“”]/u', $payload, $quotedNameMatch))
        {
            $quotedName = trim((string) ($quotedNameMatch['name'] ?? ''));
            $payload = trim(str_replace($quotedNameMatch[0], '', $payload));
        }

        $categoryName = null;
        if (preg_match('/categor[ií]a\s+([^.,]+)/iu', $payload, $categoryMatch))
        {
            $categoryName = trim($categoryMatch[1]);
            $payload = trim(str_replace($categoryMatch[0], '', $payload));
        }

        $email = null;
        if (preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $payload, $emailMatch))
        {
            $email = strtolower($emailMatch[0]);
            $payload = trim(str_replace($emailMatch[0], '', $payload));
        }

        $phone = null;
        if (preg_match('/(?:\+\d[\d\s\-()]{5,}\d|\b\d{6,}\b)/u', $payload, $phoneMatch))
        {
            $digits = preg_replace('/[^0-9]/', '', $phoneMatch[0]) ?? '';
            if (strlen($digits) >= 6)
            {
                $phone = $digits;
                $payload = trim(str_replace($phoneMatch[0], '', $payload));
            }
        }

        $notes = null;
        if (preg_match('/\bnota[s]?\b\s*[:\-]?\s*(.+?)(?=(?:,|\.\s*|\s+cumplea(?:ñ|n)os\b|\s+fecha\s+de\s+cumplea(?:ñ|n)os\b|$))/iu', $payload, $notesMatch))
        {
            $notes = trim((string) ($notesMatch[1] ?? ''));
            $payload = trim(str_replace($notesMatch[0], '', $payload));
        }

        $birthday = null;
        if (preg_match('/\b(?:cumplea(?:ñ|n)os|fecha\s+de\s+cumplea(?:ñ|n)os|birthday)\b\s*[:\-]?\s*([0-9]{1,4}[\/\-][0-9]{1,2}[\/\-][0-9]{1,4})/iu', $payload, $birthdayMatch))
        {
            $birthday = $this->normalizeBirthday(trim((string) ($birthdayMatch[1] ?? '')));
            $payload = trim(str_replace($birthdayMatch[0], '', $payload));
        }

        $name = $quotedName;
        if ($name === null || $name === '')
        {
            $name = preg_replace('/agregar\s+a\s+contacto.*$/iu', '', $payload);
            $name = preg_replace('/\bde\s+la\s+empresa\b.*$/iu', '', $name);
            $name = preg_replace('/\bpodr[aá]s\s+ingresar\s+el\s+contacto\b/iu', '', $name);
            $name = preg_replace('/\bingresar\s+el\s+contacto\b/iu', '', $name);
            $name = preg_replace('/\bnuevo\s+contacto\b[:\s-]*/iu', '', $name);
            $name = preg_replace('/\ben\s+la\b$/iu', '', $name);
            $name = trim((string) $name, " \t\n\r\0\x0B,.");
        }

        if ($name === '')
        {
            return null;
        }

        $result = ['name' => $name];
        if ($email !== null && $email !== '')
        {
            $result['email'] = $email;
        }
        if ($phone !== null && $phone !== '')
        {
            $result['phone'] = $phone;
        }
        if ($categoryName !== null && $categoryName !== '')
        {
            $result['category_name'] = $categoryName;
        }
        if ($notes !== null && $notes !== '')
        {
            $result['notes'] = $notes;
        }
        if ($birthday !== null && $birthday !== '')
        {
            $result['birthday'] = $birthday;
        }

        return $result;
    }

    private function normalizeBirthday(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '')
        {
            return null;
        }

        try
        {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1)
            {
                return Carbon::createFromFormat('Y-m-d', $raw)->format('Y-m-d');
            }

            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $raw) === 1)
            {
                return Carbon::createFromFormat('d/m/Y', $raw)->format('Y-m-d');
            }

            if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $raw) === 1)
            {
                return Carbon::createFromFormat('d-m-Y', $raw)->format('Y-m-d');
            }
        } catch (\Throwable)
        {
            return null;
        }

        return null;
    }

    /**
     * @param  array{name: string, email?: string, phone?: string, category_name?: string, notes?: string, birthday?: string}  $parsed
     * @param  array{contact_id: int, created: bool, already_exists: bool}  $creation
     */
    private function formatWhatsAppReply(array $parsed, string $toolResult, array $creation): string
    {
        $name = $parsed['name'];
        $contactId = $creation['contact_id'];

        if ($creation['already_exists'])
        {
            $lines = ["✅ *{$name}* ya estaba en el sistema (id {$contactId})."];
        } else
        {
            $lines = ["✅ *{$name}* creado (id {$contactId})."];
        }

        if (
            ! empty($parsed['category_name'])
            && (stripos($toolResult, 'Assigned to category') !== false || stripos($toolResult, 'assigned to category') !== false)
        ) {
            $lines[] = 'Asignado a la categoría *'.$parsed['category_name'].'*.';
        }

        if (! empty($parsed['email']))
        {
            $lines[] = 'Email: '.$parsed['email'];
        }

        if (! empty($parsed['phone']))
        {
            $lines[] = 'Teléfono: '.$parsed['phone'];
        }
        if (! empty($parsed['birthday']))
        {
            $lines[] = 'Cumpleaños: '.$parsed['birthday'];
        }
        if (! empty($parsed['notes']))
        {
            $lines[] = 'Nota: '.$parsed['notes'];
        }

        return implode("\n", $lines);
    }
}
