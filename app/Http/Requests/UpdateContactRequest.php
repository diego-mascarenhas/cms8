<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Asume que ya tienes lógica de autorización en otro lugar
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'status_id' => 'required|exists:contact_statuses,id',
            'phone' => 'nullable|string|max:20',
            'language' => 'required|string|max:255',
            'country' => 'nullable|string|max:2',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        
        $contactData = [
            'name' => $validated['name'],
            'status_id' => $validated['status_id'],
            'country' => $validated['country'],
            'language' => $validated['language'],
        ];

        // $sourcesData = [
        //     ['source_id' => 1, 'value' => $validated['email']],
        // ];
        // if (!empty($validated['phone'])) {
        //     $sourcesData[] = ['source_id' => 2, 'value' => $validated['phone']];
        // }
        $sourcesData = [];

        return [
            'contact' => $contactData,
            'sources' => $sourcesData,
        ];
    }
}