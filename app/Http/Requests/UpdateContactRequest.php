<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Enterprise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'surname' => 'nullable|string|max:255',
            'email' => 'nullable|email:rfc|max:255',
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-\(\)]+$/', 'min:7', 'max:25'],
            'birthday' => 'nullable|date',
            'status_id' => 'required|exists:contact_statuses,id',
            'country' => 'required|string|max:3',
            'language' => 'required|string|max:2',
            'profile' => 'nullable|string',
            'contact.user_id' => 'nullable|exists:users,id',
            'enterprise.name' => 'nullable|string|max:255',
            'enterprise.code' => 'nullable|string|max:255',
            'enterprise.website' => 'nullable|max:255',  // !FIXME: Add url validation
            'enterprise.phone' => 'nullable|string|max:20',
            'enterprise.email' => 'nullable|email:rfc|max:255',
            'enterprise.whatsapp' => 'nullable|string|max:20',
            'enterprise.enterprise_id' => [
                'nullable',
                'integer',
                Rule::exists('enterprises', 'id')->where(function ($q)
                {
                    $teamId = auth()->user()->current_team_id;
                    if ($teamId)
                    {
                        $q->where('team_id', $teamId);
                    } else
                    {
                        $q->whereRaw('1 = 0');
                    }
                }),
            ],
            'source_id' => 'array',
            'source_id.*' => 'required|exists:sources,id',
            'source_value' => 'array',
            'source_value.*' => 'required|string|max:255',
            'responsible_id' => 'required|exists:users,id',
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
            'software_ids' => 'array',
            'software_ids.*' => 'exists:software,id',
            'chat_assistant_ai_enabled' => 'nullable|boolean',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        $contactData = [
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'birthday' => $validated['birthday'],
            'status_id' => $validated['status_id'],
            'country' => $validated['country'],
            'language' => $validated['language'],
            'profile' => $validated['profile'] ?? null,
        ];

        // Add user_id if it exists in the contact array
        if (isset($validated['contact']) && isset($validated['contact']['user_id']))
        {
            $contactData['user_id'] = $validated['contact']['user_id'];
        }

        $contact = $this->route('id')
            ? Contact::findOrFail($this->route('id'))
            : new Contact;

        $enterpriseData = [];
        $sourcesData = [];
        $categories = $validated['categories'] ?? [];
        $softwareIds = $validated['software_ids'] ?? [];

        if (isset($validated['enterprise']))
        {
            $enterpriseData = [
                'name' => $validated['enterprise']['name'] ?? $contact->name,
                'code' => $validated['enterprise']['code'] ?? $contact->code,
                'website' => $validated['enterprise']['website'] ?? null,
                'phone' => $validated['enterprise']['phone'] ?? null,
                'email' => $validated['enterprise']['email'] ?? null,
                'whatsapp' => $validated['enterprise']['whatsapp'] ?? null,
                'status_id' => $validated['status_id'] == 5 ? 2 : 1,
                'responsible_id' => $validated['responsible_id'] ?? $contact->responsible_id ?? null,
            ];
        }

        if ($contact->exists)
        {
            $enterprise = null;
            $fromSelectId = isset($validated['enterprise']['enterprise_id']) ? (int) $validated['enterprise']['enterprise_id'] : 0;

            if ($fromSelectId > 0)
            {
                $enterprise = Enterprise::query()
                    ->where('id', $fromSelectId)
                    ->where('team_id', $contact->team_id)
                    ->first();
            }

            if (! $enterprise)
            {
                if (! $contact->relationLoaded('enterprises'))
                {
                    $contact->load('enterprises');
                }

                if ($contact->current_enterprise_id)
                {
                    $enterprise = $contact->enterprises->firstWhere('id', $contact->current_enterprise_id);

                    if ($enterprise)
                    {
                        if (! empty($validated['enterprise']['name']))
                        {
                            $enterprise->update($enterpriseData);
                        }
                    }
                }

                if (! $enterprise && $contact->enterprises->isNotEmpty())
                {
                    $enterprise = $contact->enterprises->first();

                    if ($enterprise && ! empty($validated['enterprise']['name']))
                    {
                        $enterprise->update($enterpriseData);
                    }
                }

                if (! $enterprise && ! empty($validated['enterprise']['code']))
                {
                    $enterprise = Enterprise::withTrashed()
                        ->where('code', $validated['enterprise']['code'])
                        ->where('team_id', $contact->team_id)
                        ->first();

                    if ($enterprise)
                    {
                        if ($enterprise->trashed())
                        {
                            $enterprise->restore();
                        }
                        $enterprise->update($enterpriseData);
                    }
                }

                if (! $enterprise && ! empty($validated['enterprise']['name']))
                {
                    $enterpriseData['team_id'] = $contact->team_id;
                    $enterprise = Enterprise::create($enterpriseData);
                }
            }

            // Store enterprise_id for syncing the many-to-many relationship
            if (isset($enterprise))
            {
                $enterpriseData['enterprise_id'] = $enterprise->id;
                // Set current_enterprise_id if contact is a client (status_id = 5) or maintain existing
                $contactData['current_enterprise_id'] = $validated['status_id'] == 5 ? $enterprise->id : $contact->current_enterprise_id;
            } else
            {
                $contactData['current_enterprise_id'] = $contact->current_enterprise_id;
            }

            if (isset($validated['source_id']) && isset($validated['source_value']))
            {
                foreach ($validated['source_id'] as $key => $sourceId)
                {
                    if (isset($validated['source_value'][$key]))
                    {
                        $sourcesData[] = [
                            'source_id' => $sourceId,
                            'value' => $validated['source_value'][$key],
                        ];
                    }
                }
            }

            $contact->sources()->detach();

            foreach ($sourcesData as $source)
            {
                $contact->sources()->attach($source['source_id'], ['value' => $source['value']]);
            }
        }

        $existingJson = json_decode(json_encode($contact->exists ? ($contact->data ?? new \stdClass) : new \stdClass), true);
        if (! is_array($existingJson))
        {
            $existingJson = [];
        }
        $existingJson['chat_assistant_ai_enabled'] = $this->boolean('chat_assistant_ai_enabled');
        $contactData['data'] = (object) $existingJson;

        return [
            'contact' => $contactData,
            'enterprise' => $enterpriseData,
            'sources' => $sourcesData,
            'categories' => $categories,
            'software_ids' => $softwareIds,
        ];
    }
}
