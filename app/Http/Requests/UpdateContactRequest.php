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
        return [
            'name' => 'required|string|max:255',
            'birthday' => 'nullable|date',
            'status_id' => 'required|exists:contact_statuses,id',
            'country' => 'required|string|max:3',
            'language' => 'required|string|max:2',
            'profile' => 'nullable|string',
            'enterprise.name' => 'nullable|string|max:255',
            'enterprise.website' => 'nullable|url|max:255',
            'enterprise.phone' => 'nullable|string|max:20',
            'enterprise.email' => 'nullable|email|max:255',
            'enterprise.whatsapp' => 'nullable|string|max:20',
            'source_id' => 'array',
            'source_id.*' => 'required|exists:sources,id',
            'source_value' => 'array',
            'source_value.*' => 'required|string|max:255',
        ];
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

        $enterpriseData = [];
        if (isset($validated['enterprise'])) {
            $enterpriseData = [
                'name' => $validated['enterprise']['name'] ?? $contact->name,
                'website' => $validated['enterprise']['website'] ?? null,
                'phone' => $validated['enterprise']['phone'] ?? null,
                'email' => $validated['enterprise']['email'] ?? null,
                'whatsapp' => $validated['enterprise']['whatsapp'] ?? null,
                'status_id' => $validated['status_id'] == 5 ? 2 : 1,
                'responsible_id' => $contact->id
            ];
        }

        $enterprise = Enterprise::withTrashed()->firstWhere('responsible_id', $contact->id);

        if ($enterprise) {
            if ($enterprise->trashed()) {
                $enterprise->restore();
            }
            $enterprise->update($enterpriseData);
        } else {
            $enterpriseData['responsible_id'] = $contact->id;
            $enterpriseData['team_id'] = $contact->team_id;
            $enterprise = Enterprise::create($enterpriseData);
        }

        $contactData['enterprise_id'] = $validated['status_id'] == 5 ? $enterprise->id : null;

        $sourcesData = [];
        if (isset($validated['source_id']) && isset($validated['source_value'])) {
            foreach ($validated['source_id'] as $key => $sourceId) {
                if (isset($validated['source_value'][$key])) {
                    $sourcesData[] = [
                        'source_id' => $sourceId,
                        'value' => $validated['source_value'][$key]
                    ];
                }
            }
        }

        $contact->sources()->detach();

        foreach ($sourcesData as $source) {
            $contact->sources()->attach($source['source_id'], ['value' => $source['value']]);
        }

        return [
            'contact' => $contactData,
            'enterprise' => $enterpriseData,
            'sources' => $sourcesData,
        ];
    }
}
