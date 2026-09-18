<?php

namespace App\Http\Requests\Api;

use App\Enums\CommunicationChannel;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->team() !== null;
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');
        if (is_string($metadata) && $metadata !== '')
        {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded))
            {
                $this->merge(['metadata' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) ($this->team()?->id ?? 0);

        return [
            'channel' => ['required', Rule::enum(CommunicationChannel::class)],
            'recipient_email' => ['nullable', 'email:rfc', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:32'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('team_id', $teamId)),
            ],
            'metadata' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'channel.required' => __('Indicá el canal.'),
            'channel.enum' => __('El canal no es válido.'),
            'message.required' => __('El mensaje es obligatorio.'),
            'recipient_email.email' => __('Ingresá un email válido.'),
            'attachments.max' => __('Podés adjuntar hasta 5 archivos.'),
            'attachments.*.max' => __('Cada archivo puede pesar hasta 10 MB.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $channelValue = $this->input('channel');
            $channel = is_string($channelValue) ? CommunicationChannel::tryFrom($channelValue) : null;
            if (! $channel)
            {
                return;
            }

            if ($channel->requiresEmail() && ! $this->filled('recipient_email'))
            {
                $validator->errors()->add('recipient_email', __('El email del destinatario es obligatorio.'));
            }

            if ($channel->requiresPhone())
            {
                $digits = preg_replace('/[^0-9]/', '', (string) $this->input('recipient_phone'));
                if (strlen((string) $digits) < 10 || strlen((string) $digits) > 15)
                {
                    $validator->errors()->add('recipient_phone', __('El teléfono debe tener entre 10 y 15 dígitos.'));
                }
            }

            if ($channel->requiresEmail() && ! $this->filled('subject'))
            {
                $validator->errors()->add('subject', __('El asunto es obligatorio.'));
            }

            $attachments = $this->file('attachments', []);
            if (! $channel->allowsAttachments() && is_array($attachments) && count($attachments) > 0)
            {
                $validator->errors()->add('attachments', __('Este canal no admite adjuntos.'));
            }
        });
    }

    public function team(): ?Team
    {
        $fromToken = $this->attributes->get('team');
        if ($fromToken instanceof Team)
        {
            return $fromToken;
        }

        return $this->user()?->currentTeam;
    }
}
