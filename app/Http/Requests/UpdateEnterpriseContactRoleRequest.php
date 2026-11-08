<?php

namespace App\Http\Requests;

use App\Models\ContactEnterprise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnterpriseContactRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $role = trim((string) $this->input('role', ''));

        $this->merge([
            'role' => $role !== '' ? $role : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['present', 'nullable', 'string', Rule::in(ContactEnterprise::ROLES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'El rol no es válido.',
        ];
    }
}
