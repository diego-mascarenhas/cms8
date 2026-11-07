<?php

namespace App\Services;

use App\Enums\MeetingTranscriptStatus;
use App\Models\MeetingTranscript;
use App\Models\MeetingVoiceProfile;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Throwable;

class MeetingTranscriptService
{
    public function __construct(private MeetingCalendarSync $calendar) {}

    public function transcribe(MeetingTranscript $meeting): void
    {
        $meeting->forceFill([
            'status' => MeetingTranscriptStatus::Processing,
            'error' => null,
        ])->save();

        try
        {
            $payload = $this->requestTranscript($meeting);
            $segments = collect($payload['words'] ?? [])
                ->filter(fn (array $word): bool => ($word['type'] ?? '') === 'word')
                ->map(fn (array $word): TranscriptionSegment => new TranscriptionSegment(
                    (string) ($word['text'] ?? ''),
                    (string) ($word['speaker_id'] ?? 'speaker_0'),
                    (float) ($word['start'] ?? 0),
                    (float) ($word['end'] ?? 0),
                ))
                ->all();
            $text = trim((string) ($payload['text'] ?? ''));
            $turns = $this->turnsFromSegments($segments);

            if ($turns === [] && $text !== '')
            {
                $turns = [[
                    'speaker' => 'speaker_0',
                    'label' => 'Persona 1',
                    'text' => $text,
                    'start_seconds' => 0,
                    'end_seconds' => 0,
                ]];
            }

            $meeting->forceFill([
                'status' => MeetingTranscriptStatus::Ready,
                'transcript' => $text,
                'turns' => $this->applyKnownSpeakers($meeting, $turns),
                'error' => null,
            ])->save();
            $this->calendar->sync($meeting);
        } catch (Throwable $exception)
        {
            $meeting->forceFill([
                'status' => MeetingTranscriptStatus::Failed,
                'error' => $this->failureMessage($exception),
            ])->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $turns
     */
    public function linkSpeaker(MeetingTranscript $meeting, string $speaker, ?int $contactId): bool
    {
        $turns = $meeting->turns ?? [];
        $matched = false;

        foreach ($turns as $index => $turn)
        {
            if (($turn['speaker'] ?? '') !== $speaker)
            {
                continue;
            }

            $matched = true;
            if ($contactId === null)
            {
                unset($turns[$index]['contact_id']);
            } else
            {
                $turns[$index]['contact_id'] = $contactId;
            }
        }

        if (! $matched)
        {
            return false;
        }

        $meeting->turns = array_values($turns);
        $meeting->save();
        $this->rememberVoice($meeting, $speaker, $contactId);
        $this->calendar->sync($meeting);

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $turns
     * @return list<array<string, mixed>>
     */
    public function applyKnownSpeakers(MeetingTranscript $meeting, array $turns): array
    {
        $profiles = MeetingVoiceProfile::withoutGlobalScope('team')
            ->where('team_id', $meeting->team_id)
            ->whereNotNull('provider_speaker_id')
            ->get()
            ->keyBy('provider_speaker_id');

        foreach ($turns as $index => $turn)
        {
            $speaker = (string) ($turn['speaker'] ?? '');
            $profile = $profiles->get($speaker);
            if ($profile === null || $this->isTemporarySpeaker($speaker))
            {
                continue;
            }

            $turns[$index]['contact_id'] = $profile->contact_id;
        }

        return $turns;
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

    /**
     * @return array<string, mixed>
     */
    private function requestTranscript(MeetingTranscript $meeting): array
    {
        $fields = [
            'model_id' => 'scribe_v2',
            'diarize' => 'true',
            'use_speaker_library' => 'true',
        ];
        if (is_string($meeting->language) && $meeting->language !== '')
        {
            $fields['language_code'] = $meeting->language;
        }

        $response = Http::withHeaders([
            'xi-api-key' => (string) config('ai.providers.eleven.key'),
        ])->timeout(600)->attach(
            'file',
            Storage::disk('local')->get($meeting->audio_path),
            basename($meeting->audio_path),
            ['Content-Type' => Storage::disk('local')->mimeType($meeting->audio_path) ?: 'application/octet-stream'],
        )->post('https://api.elevenlabs.io/v1/speech-to-text', $fields)->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function rememberVoice(MeetingTranscript $meeting, string $speaker, ?int $contactId): void
    {
        if ($contactId === null || $this->isTemporarySpeaker($speaker))
        {
            return;
        }

        MeetingVoiceProfile::withoutGlobalScope('team')->updateOrCreate(
            [
                'team_id' => $meeting->team_id,
                'contact_id' => $contactId,
            ],
            [
                'provider_speaker_id' => $speaker,
            ],
        );
    }

    private function isTemporarySpeaker(string $speaker): bool
    {
        return preg_match('/^speaker_\d+$/', $speaker) === 1;
    }
}
