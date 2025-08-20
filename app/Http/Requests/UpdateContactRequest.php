<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\Enterprise;
use Illuminate\Foundation\Http\FormRequest;

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
			'email' => 'required|email|max:255',
			'phone' => 'nullable|numeric|digits_between:7,15',
			'birthday' => 'nullable|date',
			'status_id' => 'required|exists:contact_statuses,id',
			'country' => 'required|string|max:3',
			'language' => 'required|string|max:2',
			'profile' => 'nullable|string',
			'contact.user_id' => 'nullable|exists:users,id',
			'enterprise.name' => 'nullable|string|max:255',
			'enterprise.code' => 'nullable|string|max:255',
			'enterprise.website' => 'nullable|max:255', // !FIXME: Add url validation
			'enterprise.phone' => 'nullable|string|max:20',
			'enterprise.email' => 'nullable|email|max:255',
			'enterprise.whatsapp' => 'nullable|string|max:20',
			'source_id' => 'array',
			'source_id.*' => 'required|exists:sources,id',
			'source_value' => 'array',
			'source_value.*' => 'required|string|max:255',
			'responsible_id' => 'required|exists:users,id',
			'categories' => 'array',
			'categories.*' => 'exists:categories,id',
			'software_ids' => 'array',
			'software_ids.*' => 'exists:software,id',
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
				'responsible_id' => $contact->id,
			];
		}

		if ($contact->exists)
		{
			$enterprise = Enterprise::withTrashed()
				->where('responsible_id', $contact->id)
				->where('team_id', $contact->team_id)
				->first();

			if ($enterprise)
			{
				if ($enterprise->trashed())
				{
					$enterprise->restore();
				}
				$enterprise->update($enterpriseData);
			} elseif (! empty($validated['enterprise']['name']))
			{
				$enterpriseData['team_id'] = $contact->team_id;
				$enterprise = Enterprise::create($enterpriseData);
			}

			if (isset($enterprise))
			{
				$contactData['enterprise_id'] = $validated['status_id'] == 5 ? $enterprise->id : null;
			} else
			{
				$contactData['enterprise_id'] = null;
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

		return [
			'contact' => $contactData,
			'enterprise' => $enterpriseData,
			'sources' => $sourcesData,
			'categories' => $categories,
			'software_ids' => $softwareIds,
		];
	}
}
