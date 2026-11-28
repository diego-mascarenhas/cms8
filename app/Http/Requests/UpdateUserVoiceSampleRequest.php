<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserVoiceSampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'voice_sample' => ['required', 'file', 'mimes:mp3,wav,m4a,mp4,webm,ogg,mpeg', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'voice_sample.required' => __('Choose a voice sample.'),
            'voice_sample.file' => __('The voice sample must be an audio file.'),
            'voice_sample.mimes' => __('Use MP3, WAV, M4A, MP4, WebM or OGG.'),
            'voice_sample.max' => __('The voice sample cannot be larger than 10 MB.'),
        ];
    }
}
