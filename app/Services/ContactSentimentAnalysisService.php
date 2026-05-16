<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactSentiment;
use App\Models\ContactSentimentHistory;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;

use function Laravel\Ai\agent;

class ContactSentimentAnalysisService
{
    private const SENTIMENT_INSTRUCTIONS = <<<'PROMPT'
You are a sentiment classifier. Classify the emotional tone of the message into exactly one of these categories:

1 = Muy Negativo (very negative, angry, furious, outraged)
2 = Negativo (negative, annoyed, dissatisfied, worried)
3 = Neutral (neutral, factual, no clear emotion)
4 = Positivo (positive, satisfied, grateful, friendly)
5 = Muy Positivo (very positive, delighted, enthusiastic, celebrating)

Respond with ONLY a valid JSON object, no markdown, no code block, no extra text. Example:
{"sentiment_id": 4, "reason": "El mensaje expresa gratitud y satisfacción"}

Valid keys: sentiment_id (integer 1-5), reason (short string in the same language as the message).
PROMPT;

    /**
     * Classify message text into one of the 5 emotional states using AI (Laravel AI / Anthropic).
     * Returns array with sentiment_id (1-5), name, reason or null on failure.
     *
     * @return array{id: int, name: string, reason: string}|null
     */
    public function analyzeWithAi(string $text, ?int $teamId = null): ?array
    {
        $text = trim(strip_tags($text));
        if ($text === '')
        {
            return null;
        }

        try
        {
            $agent = agent(
                instructions: self::SENTIMENT_INSTRUCTIONS,
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($text, [], Lab::Anthropic);

            if ($teamId !== null)
            {
                TokenUsageLogService::logFromAiResponse(
                    teamId: $teamId,
                    service: 'ContactSentimentAnalysisService',
                    usage: $response->usage ?? null,
                    moduleKey: 'contacts',
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

            $sentiment = ContactSentiment::find($id);
            $reason = isset($data['reason']) && is_string($data['reason'])
                ? trim($data['reason'])
                : 'Análisis automático';

            return [
                'id' => $id,
                'name' => $sentiment ? $sentiment->name : 'Neutral',
                'reason' => $reason,
            ];
        } catch (\Throwable $e)
        {
            Log::error('ContactSentimentAnalysis: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Analyze text with AI and record sentiment for the contact (all channels).
     */
    public function recordForContact(Contact $contact, string $text, string $channel): void
    {
        $result = $this->analyzeWithAi($text, (int) $contact->team_id);

        if ($result === null)
        {
            return;
        }

        ContactSentimentHistory::create([
            'contact_id' => $contact->id,
            'sentiment_id' => $result['id'],
            'notes' => sprintf('Análisis automático de %s: %s', $channel, $result['reason']),
        ]);

        Log::info('Contact sentiment recorded', [
            'contact_id' => $contact->id,
            'sentiment_id' => $result['id'],
            'channel' => $channel,
        ]);
    }
}
