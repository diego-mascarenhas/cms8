<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DetectExpenseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
