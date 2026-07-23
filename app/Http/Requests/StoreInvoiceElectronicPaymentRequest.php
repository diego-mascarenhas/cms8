<?php

namespace App\Http\Requests;

use App\Models\PaymentSync;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceElectronicPaymentRequest extends FormRequest
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
        $teamId = (int) auth()->user()->currentTeam->id;

        return [
            'payment_sync_id' => [
                'required',
                'integer',
                Rule::exists('payment_syncs', 'id')->where(
                    fn ($query) => $query
                        ->where('team_id', $teamId)
                        ->where('provider', 'mercadopago')
                        ->whereRaw('LOWER(status) = ?', ['approved']),
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_sync_id.required' => __('invoice_payment.errors.sync_required'),
            'payment_sync_id.exists' => __('invoice_payment.errors.sync_invalid'),
        ];
    }

    public function paymentSync(): PaymentSync
    {
        return PaymentSync::query()->findOrFail((int) $this->validated('payment_sync_id'));
    }
}
