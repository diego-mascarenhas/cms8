<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MeetingSpeakerLibrary
{
    private string $endpoint = 'https://api.elevenlabs.io/v1/speech-to-text/speakers';

    public function enroll(User $user): void
    {
        $path = $user->voice_sample_path;
        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path))
        {
            return;
        }

        if ($this->key() === '')
        {
            return;
        }

        $speakerId = $this->speakerId($user);
        $previous = is_string($user->voice_speaker_id) ? $user->voice_speaker_id : '';
        if ($previous !== '' && $previous !== $speakerId)
        {
            $this->forget($previous);
        }

        $this->forget($speakerId);
        $response = $this->create($user, $speakerId, $path);
        if ($response === null)
        {
            return;
        }
        if ($response->status() === 409)
        {
            $this->forget($speakerId);
            $response = $this->create($user, $speakerId, $path);
        }

        $response->throw();
        $returned = $response->json('speaker_id');
        if (! is_string($returned) || $returned === '')
        {
            $returned = $response->json('id');
        }

        $user->forceFill([
            'voice_speaker_id' => is_string($returned) && $returned !== '' ? $returned : $speakerId,
        ])->save();
    }

    public function forgetUser(User $user): void
    {
        $stored = is_string($user->voice_speaker_id) ? $user->voice_speaker_id : '';
        if ($stored === '')
        {
            return;
        }

        $this->forget($stored);
        $this->forget($this->speakerId($user));
    }

    public function forget(string $speakerId): void
    {
        $speakerId = trim($speakerId);
        if ($speakerId === '' || $this->key() === '')
        {
            return;
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->delete($this->endpoint.'/'.rawurlencode($speakerId));

        if ($response->status() === 404 || $response->successful())
        {
            return;
        }

        $response->throw();
    }

    public function speakerId(User $user): string
    {
        return 'user-'.$user->id;
    }

    private function create(User $user, string $speakerId, string $path): ?Response
    {
        $bytes = Storage::disk('local')->get($path);
        if (! is_string($bytes) || $bytes === '')
        {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'mp3';
        $mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

        return Http::withHeaders($this->headers())
            ->timeout(120)
            ->attach(
                'file',
                $bytes,
                'voice-sample.'.$extension,
                ['Content-Type' => $mime],
            )
            ->post($this->endpoint, [
                'name' => mb_substr($user->name, 0, 80),
                'speaker_id' => $speakerId,
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'xi-api-key' => $this->key(),
        ];
    }

    private function key(): string
    {
        return (string) config('ai.providers.eleven.key');
    }
}
