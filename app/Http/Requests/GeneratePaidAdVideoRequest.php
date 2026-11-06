<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePaidAdVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:80'],
            'body' => ['nullable', 'string', 'max:280'],
            'voice' => ['required', Rule::in(['female', 'male'])],
            'voice_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{10,40}$/'],
            'scenes' => ['required', 'array', 'size:3'],
            'scenes.*.caption' => ['required', 'string', 'min:2', 'max:80'],
            'scenes.*.spoken' => ['required', 'string', 'min:8', 'max:240'],
            'scenes.*.visual' => ['required', 'string', 'min:10', 'max:400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'voice.required' => __('Elegí una voz.'),
            'voice.in' => __('Elegí una voz.'),
            'voice_id.regex' => __('El Voice ID de ElevenLabs no es válido.'),
            'scenes.size' => __('El vídeo necesita tres escenas.'),
            'scenes.*.caption.required' => __('Cada escena necesita el texto en pantalla.'),
            'scenes.*.spoken.required' => __('Cada escena necesita lo que dice la voz.'),
            'scenes.*.spoken.min' => __('La frase de la voz es demasiado corta.'),
            'scenes.*.visual.required' => __('Cada escena necesita una descripción del plano.'),
            'scenes.*.visual.min' => __('La descripción del plano es demasiado corta.'),
        ];
    }
}
