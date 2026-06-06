<?php

namespace App\Http\Requests;

use App\Services\Finance\InvoiceCreditNoteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceCreditNoteRequest extends FormRequest
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
            'reason' => [
                'required',
                'string',
                Rule::in(InvoiceCreditNoteService::STRIPE_REASONS),
            ],
        ];
    }
}
