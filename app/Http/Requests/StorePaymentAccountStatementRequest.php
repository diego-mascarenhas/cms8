<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentAccountStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('paymentAccount');

        return $this->user()?->can('update', $account) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:25600'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'period' => ['nullable', 'regex:/^(m:([1-9]|1[0-2])|q:[1-4])$/'],
            'statement_balance' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => __('Subí al menos un extracto.'),
            'files.*.max' => __('Cada archivo puede pesar hasta 25 MB.'),
            'period.regex' => __('Elegí un mes o un trimestre.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $year = $this->input('period_year');
            $month = $this->input('period_month');
            $period = (string) $this->input('period');

            if ($period !== '')
            {
                if (blank($year))
                {
                    $validator->errors()->add('period_year', __('Indicá el año del extracto.'));
                }

                return;
            }

            if ((filled($year) && blank($month)) || (blank($year) && filled($month)))
            {
                $validator->errors()->add(
                    'period_month',
                    __('Indicá año y mes juntos, o dejá ambos vacíos para detectarlos automáticamente.'),
                );
            }
        });
    }
}
