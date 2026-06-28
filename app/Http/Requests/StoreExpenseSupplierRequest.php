<?php

namespace App\Http\Requests;

use App\Services\ExpenseSupplierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreExpenseSupplierRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:75'],
            'identification_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[+\-\d\s()]+$/'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'locality' => ['nullable', 'string', 'max:50'],
            'province' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'identification_number' => trim((string) $this->input('identification_number', '')),
            'email' => trim((string) $this->input('email', '')),
            'phone' => trim((string) $this->input('phone', '')),
            'website' => trim((string) $this->input('website', '')),
            'address' => trim((string) $this->input('address', '')),
            'postal_code' => trim((string) $this->input('postal_code', '')),
            'locality' => trim((string) $this->input('locality', '')),
            'province' => trim((string) $this->input('province', '')),
            'country' => strtoupper(trim((string) $this->input('country', 'ES'))) ?: 'ES',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'El teléfono solo puede contener números, espacios y los símbolos + - ( ).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $phone = trim((string) $this->input('phone', ''));
            if ($phone !== '')
            {
                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                if (strlen($digits) < 9 || strlen($digits) > 15)
                {
                    $validator->errors()->add(
                        'phone',
                        'Introduce un teléfono válido (entre 9 y 15 dígitos).',
                    );
                }
            }

            if ($validator->errors()->isNotEmpty())
            {
                return;
            }

            $teamId = (int) auth()->user()->currentTeam->id;
            $service = app(ExpenseSupplierService::class);

            if ($service->matchesOwnBusiness($this->all(), $teamId))
            {
                $validator->errors()->add(
                    'name',
                    'Estos datos corresponden a la configuración de tu negocio. Indica los del proveedor que emitió la factura.',
                );
            }
        });
    }
}
