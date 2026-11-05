<?php

namespace App\Services;

use App\Enums\MeetingTranscriptStatus;
use App\Models\MeetingTranscript;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Laravel\Ai\Transcription;
use Throwable;

class MeetingTranscriptService
{
    public function transcribe(MeetingTranscript $meeting): void
    {
        $meeting->forceFill([
            'status' => MeetingTranscriptStatus::Processing,
            'error' => null,
        ])->save();

        try
        {
            $pending = Transcription::fromStorage($meeting->audio_path, 'local')
                ->diarize()
                ->timeout(600);

            if (is_string($meeting->language) && $meeting->language !== '')
            {
                $pending->language($meeting->language);
            }

            $response = $pending->generate(provider: Lab::ElevenLabs);
            $turns = $this->turnsFromSegments($response->segments->all());

            if ($turns === [] && trim($response->text) !== '')
            {
                $turns = [[
                    'speaker' => 'speaker_0',
                    'label' => 'Persona 1',
                    'text' => trim($response->text),
                    'start_seconds' => 0,
                    'end_seconds' => 0,
                ]];
            }

            $meeting->forceFill([
                'status' => MeetingTranscriptStatus::Ready,
                'transcript' => $response->text,
                'turns' => $turns,
                'error' => null,
            ])->save();
        } catch (Throwable $exception)
        {
            $meeting->forceFill([
                'status' => MeetingTranscriptStatus::Failed,
                'error' => $this->failureMessage($exception),
            ])->save();
        }
    }

    public function failureMessage(Throwable $exception): string
    {
        $detail = $exception->getMessage();

        if ($exception instanceof RequestException)
        {
            $decoded = $exception->response->json();
            $message = is_array($decoded) ? ($decoded['detail']['message'] ?? null) : null;
            if (is_string($message) && $message !== '')
            {
                $detail = $message;
            }
        }

        if (preg_match('/speech_to_text/i', $detail) === 1)
        {
            return 'La clave no tiene el permiso de transcripción. Activalo en la clave y volvé a subir el audio.';
        }

        if (preg_match('/eleven\s*labs/i', $detail) === 1)
        {
            return 'No se pudo transcribir el audio.';
        }

        return mb_substr($detail, 0, 1000);
    }

    /**
     * @param  list<TranscriptionSegment>  $segments
     * @return list<array{speaker: string, label: string, text: string, start_seconds: float, end_seconds: float}>
     */
    public function turnsFromSegments(array $segments): array
    {
        $turns = [];
        $labels = [];

        foreach ($segments as $segment)
        {
            $speaker = trim($segment->speaker) !== '' ? $segment->speaker : 'speaker_0';
            $text = trim($segment->text);
            if ($text === '')
            {
                continue;
            }

            if (! isset($labels[$speaker]))
            {
                $labels[$speaker] = 'Persona '.(count($labels) + 1);
            }

            $lastIndex = array_key_last($turns);
            if ($lastIndex !== null && $turns[$lastIndex]['speaker'] === $speaker)
            {
                $turns[$lastIndex]['text'] .= ' '.$text;
                $turns[$lastIndex]['end_seconds'] = $segment->endSeconds;

                continue;
            }

            $turns[] = [
                'speaker' => $speaker,
                'label' => $labels[$speaker],
                'text' => $text,
                'start_seconds' => $segment->startSeconds,
                'end_seconds' => $segment->endSeconds,
            ];
        }

        return $turns;
    }
}
