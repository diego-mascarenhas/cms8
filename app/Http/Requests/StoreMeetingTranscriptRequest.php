<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'language' => ['nullable', 'string', 'size:2'],
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,mp4,webm,ogg,mpeg', 'max:512000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Indicá un título para la reunión.',
            'audio.required' => 'Subí el audio de la reunión.',
            'audio.mimes' => 'El audio tiene que ser mp3, wav, m4a, mp4, webm u ogg.',
            'audio.max' => 'El audio no puede pesar más de 500 MB.',
        ];
    }
}
