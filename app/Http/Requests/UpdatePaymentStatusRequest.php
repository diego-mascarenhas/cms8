<?php

namespace App\Http\Requests;

use App\Services\Finance\PaymentStatusUpdateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentStatusRequest extends FormRequest
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
            'status' => [
                'required',
                'integer',
                Rule::in(array_keys(app(PaymentStatusUpdateService::class)->selectableStatuses())),
            ],
        ];
    }
}
