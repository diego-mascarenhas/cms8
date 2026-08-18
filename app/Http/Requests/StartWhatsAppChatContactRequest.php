<?php

namespace App\Http\Requests;

use App\Services\WhatsApp\WhatsAppInboxContactStarter;
use Illuminate\Foundation\Http\FormRequest;

class StartWhatsAppChatContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'phone' => WhatsAppInboxContactStarter::normalizeInboxPhone((string) $this->input('phone', '')),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,15}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('El nombre es obligatorio.'),
            'phone.required' => __('El teléfono es obligatorio.'),
            'phone.regex' => __('Usá el número con código de país, por ejemplo 34600111222.'),
        ];
    }
}
