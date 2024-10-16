<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Contact;
use App\Models\Enterprise;
use Illuminate\Database\Eloquent\SoftDeletes;

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

        // if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
        //     $contact = Contact::findOrFail($this->route('id'));
        //     if ($contact->status_id == 5) {
        //         $rules['status_id'] = 'required|in:5';
        //     }
        // }

        // if ($this->input('status_id') == 5) {
        //     $rules['enterprise_name'] = 'required|string|max:255';
        // }

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

        $contact = Contact::findOrFail($this->route('id'));

        $enterpriseStatus = $validated['status_id'] == 5 ? 2 : 1;
    
        if ($validated['status_id'] == 5)
        {
            $enterprise = Enterprise::withTrashed()->firstWhere('responsible_id', $contact->id);
    
            if ($enterprise)
            {
                if ($enterprise->trashed())
                {
                    $enterprise->restore();
                }

                $enterprise->update([
                    'name' => $validated['enterprise_name'] ?? $enterprise->name,
                    'status_id' => $enterpriseStatus
                ]);
            }
            else
            {
                $enterprise = Enterprise::create([
                    'responsible_id' => $contact->id,
                    'name' => $validated['enterprise_name'] ?? $contact->name,
                    'team_id' => $contact->team_id,
                    'status_id' => $enterpriseStatus
                ]);
            }
    
            $contactData['enterprise_id'] = $enterprise->id;
        }
        else
        {
            $enterprise = Enterprise::where('responsible_id', $contact->id)->first();
            
            if ($enterprise)
            {
                $enterprise->update(['status_id' => $enterpriseStatus]);
            }

            $contactData['enterprise_id'] = null;
        }

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
