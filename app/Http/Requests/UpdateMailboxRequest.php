<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMailboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', 'string', 'in:ssl,tls,none'],
            'username' => ['required', 'string'],
            'password' => ['nullable', 'string'],
            'protocol' => ['nullable', 'string', 'in:imap,imap2'],
            'folder' => ['nullable', 'string', 'max:255'],
        ];
    }
}
