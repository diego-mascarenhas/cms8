<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ImportMailerAudienceCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Elegí un archivo CSV para importar.'),
            'file.mimetypes' => __('El archivo tiene que ser un CSV.'),
            'file.max' => __('El archivo no puede superar los 5 MB.'),
        ];
    }
}
