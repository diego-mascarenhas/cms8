<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactIntent;
use App\Models\ContactSentiment;
use App\Models\ContactSentimentHistory;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class ContactSentimentAnalysisService
{
    private const INTENT_KEYS = 'buy, update, work, cancel, other, unclear';

    private const SENTIMENT_INSTRUCTIONS = <<<'PROMPT'
You classify inbound customer messages for a CRM.

Emotional tone (sentiment_id), exactly one:
1 = Muy Negativo (very negative, angry, furious, outraged)
2 = Negativo (negative, annoyed, dissatisfied, worried)
3 = Neutral (neutral, factual, no clear emotion)
4 = Positivo (positive, satisfied, grateful, friendly)
5 = Muy Positivo (very positive, delighted, enthusiastic, celebrating)

Commercial intent (intent_key), exactly one:
buy = wants to purchase a product or service
update = wants to change existing data, an order, a plan, or details
work = wants a job, repair, project, or service performed
cancel = wants to unsubscribe, cancel, or leave
other = a clear request that is none of the above
unclear = greeting, noise, or no actionable intent

Respond with ONLY a valid JSON object, no markdown. Example:
{"sentiment_id": 4, "intent_key": "buy", "reason": "Pide precio y quiere comprar"}

Valid keys: sentiment_id (integer 1-5), intent_key (buy|update|work|cancel|other|unclear), reason (short string in the message language).
PROMPT;

    private const DAILY_CONTEXT_INSTRUCTIONS = <<<'PROMPT'
You classify the last 24 hours of inbound messages from one contact (WhatsApp and/or email).

Emotional tone (sentiment_id) across the full thread, exactly one:
1 = Muy Negativo (very negative, angry, furious, outraged)
2 = Negativo (negative, annoyed, dissatisfied, worried)
3 = Neutral (neutral, factual, no clear emotion)
4 = Positivo (positive, satisfied, grateful, friendly)
5 = Muy Positivo (very positive, delighted, enthusiastic, celebrating)

Weigh the whole thread. If tone shifts, favor the most recent emotional direction unless earlier messages clearly dominate.

Commercial intent (intent_key) for what they want next, exactly one:
buy = wants to purchase a product or service
update = wants to change existing data, an order, a plan, or details
work = wants a job, repair, project, or service performed
cancel = wants to unsubscribe, cancel, or leave
other = a clear request that is none of the above
unclear = greeting, noise, or no actionable intent

Respond with ONLY a valid JSON object, no markdown. Example:
{"sentiment_id": 4, "intent_key": "work", "reason": "Pasó de quejarse a pedir que hagan la reparación"}

Valid keys: sentiment_id (integer 1-5), intent_key (buy|update|work|cancel|other|unclear), reason (short string in the messages language).
PROMPT;

    /**
     * @return array{id: int, name: string, reason: string, intent_id: int|null, intent_key: string}|null
     */
    public function analyzeWithAi(string $text, ?int $teamId = null, ?string $instructions = null): ?array
    {
        $text = trim(strip_tags($text));
        if ($text === '')
        {
            return null;
        }

        try
        {
            $agent = agent(
                instructions: $instructions ?? self::SENTIMENT_INSTRUCTIONS,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($text, [], AiTasks::provider('sentiment'));

            if ($teamId !== null)
            {
                TokenUsageLogService::logFromAiResponse(
                    teamId: $teamId,
                    service: 'ContactSentimentAnalysisService',
                    usage: $response->usage ?? null,
                    moduleKey: 'insights',
                    inputSize: strlen($text),
                );
            }

            $raw = trim($response->text ?? '');
            if ($raw === '')
            {
                Log::warning('ContactSentimentAnalysis: AI returned empty response');

                return null;
            }

            $raw = preg_replace('/^```\w*\s*|\s*```$/u', '', $raw);
            $data = json_decode($raw, true);

            if (! is_array($data) || ! isset($data['sentiment_id']))
            {
                Log::warning('ContactSentimentAnalysis: Invalid JSON from AI', ['raw' => substr($raw, 0, 200)]);

                return null;
            }

            $id = (int) $data['sentiment_id'];
            if ($id < 1 || $id > 5)
            {
                $id = 3;
            }

            $intentKey = strtolower(trim((string) ($data['intent_key'] ?? 'unclear')));
            if (! in_array($intentKey, explode(', ', self::INTENT_KEYS), true))
            {
                $intentKey = 'unclear';
            }

            $intent = ContactIntent::query()->where('key', $intentKey)->first();
            $sentiment = ContactSentiment::find($id);
            $reason = isset($data['reason']) && is_string($data['reason'])
                ? trim($data['reason'])
                : 'Análisis automático';

            return [
                'id' => $id,
                'name' => $sentiment ? $sentiment->name : 'Neutral',
                'reason' => $reason,
                'intent_id' => $intent?->id,
                'intent_key' => $intentKey,
            ];
        } catch (\Throwable $e)
        {
            Log::error('ContactSentimentAnalysis: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Analyze text with AI and record sentiment and intent for the contact.
     */
    public function recordForContact(Contact $contact, string $text, string $channel): void
    {
        $instructions = $channel === 'daily'
            ? self::DAILY_CONTEXT_INSTRUCTIONS
            : null;

        $result = $this->analyzeWithAi($text, (int) $contact->team_id, $instructions);

        if ($result === null)
        {
            return;
        }

        ContactSentimentHistory::create([
            'contact_id' => $contact->id,
            'sentiment_id' => $result['id'],
            'intent_id' => $result['intent_id'],
            'notes' => sprintf('Análisis automático de %s: %s', $channel, $result['reason']),
        ]);

        Log::info('Contact sentiment recorded', [
            'contact_id' => $contact->id,
            'sentiment_id' => $result['id'],
            'intent_id' => $result['intent_id'],
            'channel' => $channel,
        ]);
    }
}
