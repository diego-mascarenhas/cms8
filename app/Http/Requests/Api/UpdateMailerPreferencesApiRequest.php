<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMailerPreferencesApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'min_hours_between_emails' => ['required', 'integer', 'min:0', 'max:8760'],
            'enable_open_tracking' => ['required', 'boolean'],
            'enable_click_tracking' => ['required', 'boolean'],
            'show_unsubscribe' => ['required', 'boolean'],
            'skip_weekends' => ['required', 'boolean'],
            'use_contact_business_hours' => ['required', 'boolean'],
            'business_hours_start' => ['required', 'date_format:H:i'],
            'business_hours_end' => ['required', 'date_format:H:i', 'after:business_hours_start'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'min_hours_between_emails.required' => __('Las horas entre correos son obligatorias.'),
            'min_hours_between_emails.integer' => __('Las horas entre correos tienen que ser un número entero.'),
            'min_hours_between_emails.min' => __('Las horas entre correos no pueden ser negativas.'),
            'min_hours_between_emails.max' => __('Las horas entre correos no pueden pasar de un año.'),
            'business_hours_end.after' => __('La hora de fin tiene que ser posterior a la de inicio.'),
        ];
    }
}
