<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Contact;

class UpdateContactRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'birthday' => 'nullable|date',
            'status_id' => 'required|exists:contact_statuses,id',
            'country' => 'required|string|max:3',
            'language' => 'required|string|max:2',
            'profile' => 'nullable|string',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $contact = Contact::findOrFail($this->route('id'));
            if ($contact->status_id == 5) {
                $rules['status_id'] = 'required|in:5';
            }
        }

        if ($this->input('status_id') == 5) {
            $rules['enterprise_name'] = 'required|string|max:255';
        }

        return $rules;
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        
        $contactData = [
            'name' => $validated['name'],
            'birthday' => $validated['birthday'],
            'status_id' => $validated['status_id'],
            'country' => $validated['country'],
            'language' => $validated['language'],
            'profile' => $validated['profile'] ?? null,
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
