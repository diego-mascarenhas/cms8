<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Team;
use App\Services\TokenUsageLogService;
use App\Services\UserResolverService;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

final class WhatsAppInboundContactRegistrationService
{
    private const FULL_NAME_INSTRUCTIONS = <<<'PROMPT'
You validate whether a WhatsApp message contains a real person's full name (given name + family name).

Accept examples: "María García López", "Pepe Suárez", "John Smith".
Reject greetings, questions, single names, nicknames only, company names, or nonsense: "Hola", "Qué tal", "Pepe", "S.L.", "123", "???".

Respond with ONLY valid JSON (no markdown):
{"valid": true, "first_name": "Pepe", "last_name": "Suárez"}
or
{"valid": false, "reason": "Short explanation in Spanish"}

Use the same language as the user message for "reason". Split multi-word surnames into last_name (e.g. "García López").
PROMPT;

    public function shouldHandleRegistration(string $phone, ?Team $team): bool
    {
        if ($team === null)
        {
            return false;
        }

        if (app(UserResolverService::class)->resolveTeamStaffByPhone((int) $team->id, $phone) !== null)
        {
            return false;
        }

        if ($this->hasRegistrationInProgress($phone))
        {
            return true;
        }

        $contact = $this->findContactByPhone($team, $phone);

        return ! $this->contactHasCompletedRegistration($contact);
    }

    public function hasRegistrationInProgress(string $phone): bool
    {
        $lastMessage = Conversation::query()
            ->where('to', $phone)
            ->where('channel', 'whatsapp')
            ->orderByDesc('created_at')
            ->first();

        if ($lastMessage === null)
        {
            return false;
        }

        $metadata = $lastMessage->metadata ?? [];

        return ($metadata['registration_step'] ?? null) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function processRegistration(string $phone, string $message, WhatsAppGateway $sender, ?Team $team): ?array
    {
        if ($team === null)
        {
            $team = $this->resolveTeamFromPhone($phone);
        }

        if ($team === null)
        {
            return null;
        }

        if (app(UserResolverService::class)->resolveTeamStaffByPhone((int) $team->id, $phone) !== null)
        {
            return null;
        }

        $lastMessage = Conversation::query()
            ->where('to', $phone)
            ->where('channel', 'whatsapp')
            ->orderByDesc('created_at')
            ->first();

        $metadata = $lastMessage?->metadata ?? [];
        $registrationStep = $metadata['registration_step'] ?? null;

        if (! $registrationStep)
        {
            if ($this->contactHasCompletedRegistration($this->findContactByPhone($team, $phone)))
            {
                return null;
            }

            $response = __('app.whatsapp_registration_ask_full_name');

            $sender->sendMessage($phone, $response, ['registration_step' => 'name']);

            return ['success' => true, 'message' => 'Registration initiated'];
        }

        if ($lastMessage === null)
        {
            return null;
        }

        if ($registrationStep === 'name')
        {
            return $this->processNameStep($phone, $message, $sender, (int) $team->id);
        }

        if ($registrationStep === 'email')
        {
            return $this->processEmailStep($phone, $message, $sender, $team, $metadata);
        }

        return null;
    }

    public function contactHasCompletedRegistration(?Contact $contact): bool
    {
        if ($contact === null)
        {
            return false;
        }

        $email = trim((string) $contact->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            return false;
        }

        if (str_ends_with(strtolower($email), '@chat.placeholder'))
        {
            return false;
        }

        $firstName = trim((string) $contact->name);
        $surname = trim((string) ($contact->surname ?? ''));

        if ($firstName === '')
        {
            return false;
        }

        if ($surname !== '')
        {
            return true;
        }

        $parts = preg_split('/\s+/u', $firstName) ?: [];

        return count($parts) >= 2
            && mb_strlen($parts[0]) >= 2
            && mb_strlen($parts[1]) >= 2;
    }

    /**
     * @return array{valid: bool, reason?: string, first_name?: string, last_name?: string}
     */
    public function validateFullName(string $rawName, ?int $teamId = null): array
    {
        $trimmed = trim($rawName);
        if ($trimmed === '')
        {
            return [
                'valid' => false,
                'reason' => __('app.whatsapp_registration_invalid_name'),
            ];
        }

        $aiResult = $this->validateFullNameWithAi($trimmed, $teamId);
        if ($aiResult !== null)
        {
            return $aiResult;
        }

        return $this->validateFullNameHeuristic($trimmed);
    }

    /**
     * @return array{valid: bool, reason?: string, first_name?: string, last_name?: string}|null
     */
    private function validateFullNameWithAi(string $name, ?int $teamId): ?array
    {
        if (! config('ai.providers.anthropic.key') && ! config('ai.providers.openai.key'))
        {
            return null;
        }

        try
        {
            $response = agent(
                instructions: self::FULL_NAME_INSTRUCTIONS,
                messages: [],
                tools: [],
            )->prompt($name, [], Lab::Anthropic);

            if ($teamId !== null)
            {
                TokenUsageLogService::logFromAiResponse(
                    teamId: $teamId,
                    service: 'WhatsAppInboundContactRegistrationService',
                    usage: $response->usage ?? null,
                    moduleKey: 'chat',
                    inputSize: strlen($name),
                );
            }

            $raw = trim($response->text ?? '');
            $raw = preg_replace('/^```\w*\s*|\s*```$/u', '', $raw);
            $data = json_decode((string) $raw, true);

            if (! is_array($data) || ! array_key_exists('valid', $data))
            {
                return null;
            }

            if (! filter_var($data['valid'], FILTER_VALIDATE_BOOLEAN))
            {
                $reason = isset($data['reason']) && is_string($data['reason']) && trim($data['reason']) !== ''
                    ? trim($data['reason'])
                    : __('app.whatsapp_registration_invalid_name');

                return ['valid' => false, 'reason' => $reason];
            }

            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));

            if ($firstName === '' || $lastName === '')
            {
                return null;
            }

            return [
                'valid' => true,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
        } catch (\Throwable $e)
        {
            Log::warning('WhatsApp registration: full name AI validation failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{valid: bool, reason?: string, first_name?: string, last_name?: string}
     */
    private function validateFullNameHeuristic(string $name): array
    {
        if (! preg_match("/^[\p{L}\p{M}'’.\\-]+(?:\\s+[\\p{L}\p{M}'’.\\-]+)+$/u", $name))
        {
            return [
                'valid' => false,
                'reason' => __('app.whatsapp_registration_invalid_name'),
            ];
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        if (count($parts) < 2)
        {
            return [
                'valid' => false,
                'reason' => __('app.whatsapp_registration_invalid_name'),
            ];
        }

        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);

        if (mb_strlen($firstName) < 2 || mb_strlen($lastName) < 2)
        {
            return [
                'valid' => false,
                'reason' => __('app.whatsapp_registration_invalid_name'),
            ];
        }

        $blocked = ['hola', 'buenas', 'buenos', 'días', 'dias', 'tardes', 'noches', 'qué', 'que', 'tal', 'hey', 'hello', 'hi'];
        $lowerParts = array_map(fn (string $part): string => mb_strtolower($part), $parts);
        foreach ($lowerParts as $part)
        {
            if (in_array($part, $blocked, true))
            {
                return [
                    'valid' => false,
                    'reason' => __('app.whatsapp_registration_invalid_name'),
                ];
            }
        }

        return [
            'valid' => true,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function processNameStep(string $phone, string $message, WhatsAppGateway $sender, int $teamId): array
    {
        $validation = $this->validateFullName($message, $teamId);

        if (! $validation['valid'])
        {
            $reason = $validation['reason'] ?? __('app.whatsapp_registration_invalid_name');
            $response = $reason."\n\n".__('app.whatsapp_registration_ask_full_name_retry');

            $sender->sendMessage($phone, $response, ['registration_step' => 'name']);

            return ['success' => true, 'message' => 'Invalid name'];
        }

        $firstName = $validation['first_name'];
        $lastName = $validation['last_name'];
        $fullName = trim($firstName.' '.$lastName);

        $response = __('app.whatsapp_registration_ask_email', ['name' => $firstName]);

        $sender->sendMessage($phone, $response, [
            'registration_step' => 'email',
            'contact_first_name' => $firstName,
            'contact_last_name' => $lastName,
            'contact_full_name' => $fullName,
        ]);

        return ['success' => true, 'message' => 'Name collected'];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function processEmailStep(string $phone, string $message, WhatsAppGateway $sender, Team $team, array $metadata): array
    {
        $email = trim($message);
        $firstName = trim((string) ($metadata['contact_first_name'] ?? ''));
        $lastName = trim((string) ($metadata['contact_last_name'] ?? ''));
        $fullName = trim((string) ($metadata['contact_full_name'] ?? trim($firstName.' '.$lastName)));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            $response = __('app.whatsapp_registration_invalid_email');

            $sender->sendMessage($phone, $response, [
                'registration_step' => 'email',
                'contact_first_name' => $firstName,
                'contact_last_name' => $lastName,
                'contact_full_name' => $fullName,
            ]);

            return ['success' => true, 'message' => 'Invalid email'];
        }

        try
        {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $creatorId = (int) ($team->user_id ?? 0);

            $contact = $this->findContactByPhone($team, $phone);

            $payload = [
                'team_id' => $team->id,
                'name' => $firstName !== '' ? $firstName : $fullName,
                'surname' => $lastName,
                'email' => $email,
                'phone' => $cleanPhone,
                'user_id' => null,
                'creator_id' => $creatorId > 0 ? $creatorId : null,
                'responsible_id' => $creatorId > 0 ? $creatorId : null,
                'status_id' => 1,
            ];

            if ($contact !== null)
            {
                $contact->update($payload);
                $contact = $contact->fresh();
            } else
            {
                $contact = Contact::withoutGlobalScopes()->create($payload);
            }

            $handoff = ! filter_var($team->getSetting('assistant_auto_respond', '1'), FILTER_VALIDATE_BOOLEAN);

            $displayName = trim($firstName !== '' ? $firstName : $fullName);
            if ($handoff)
            {
                $response = __('app.whatsapp_registration_complete_handoff', ['name' => $displayName]);
            } else
            {
                $response = __('app.whatsapp_registration_complete_active', ['name' => $displayName]);
            }

            $sender->sendMessage($phone, $response, null, null);

            return [
                'success' => true,
                'message' => 'Contact registered',
                'contact_id' => $contact->id,
                'handoff' => $handoff,
            ];
        } catch (\Throwable $e)
        {
            Log::error('WhatsApp contact registration error: '.$e->getMessage());

            $sender->sendMessage($phone, __('app.whatsapp_registration_error'));

            return ['success' => false, 'message' => 'Registration error'];
        }
    }

    private function findContactByPhone(Team $team, string $phone): ?Contact
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === '')
        {
            return null;
        }

        return Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where(function ($query) use ($digits)
            {
                $query->where('phone', $digits);
                if (strlen($digits) === 11 && str_starts_with($digits, '34'))
                {
                    $query->orWhere('phone', substr($digits, -9));
                }
                if (strlen($digits) === 9)
                {
                    $query->orWhere('phone', '34'.$digits);
                }
            })
            ->orderBy('id')
            ->first();
    }

    private function resolveTeamFromPhone(string $phone): ?Team
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if ($cleanPhone === '')
        {
            return null;
        }

        $lastInbound = Conversation::query()
            ->where('channel', 'whatsapp')
            ->where('direction', 'inbound')
            ->where(function ($query) use ($cleanPhone)
            {
                $query
                    ->where('from', $cleanPhone)
                    ->orWhere('from', 'like', $cleanPhone.':%');
            })
            ->orderByDesc('created_at')
            ->first();

        if ($lastInbound === null)
        {
            return null;
        }

        $teamNumber = preg_replace('/[^0-9]/', '', (string) $lastInbound->to);

        return $teamNumber !== '' ? Team::findByWhatsAppNumber($teamNumber) : null;
    }
}
