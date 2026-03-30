<?php

namespace App\Http\Requests;

use App\Enums\ContactInteractionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_column(ContactInteractionType::cases(), 'value');

        return [
            'type' => ['required', Rule::in($types)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'occurred_at' => ['required', 'date'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
        ];
    }
}
