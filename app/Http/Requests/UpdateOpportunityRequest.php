<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $opportunity = \App\Models\Opportunity::find($this->route('id'));

        return $opportunity && $this->user()->can('update', $opportunity);
    }

    public function rules(): array
    {
        $teamId = $this->user()->currentTeam->id;

        return [
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('team_id', $teamId)],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'opportunity_stage_id' => ['required', 'integer', 'exists:opportunity_stages,id'],
            'name' => ['required', 'string', 'max:255'],
            'opened_at' => ['required', 'date'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'offering_summary' => ['nullable', 'string'],
            'offering_kind' => ['required', Rule::in(['none', 'product', 'service'])],
            'product_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('offering_kind') === 'product'),
                Rule::exists('products', 'id')->where('team_id', $teamId),
            ],
            'service_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('offering_kind') === 'service'),
            ],
            'expected_close_at' => ['nullable', 'date'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'closed_at' => ['nullable', 'date'],
            'closed_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void
        {
            if ($this->input('offering_kind') !== 'service')
            {
                return;
            }
            $id = $this->input('service_id');
            if (! $id)
            {
                return;
            }
            $teamId = $this->user()->currentTeam->id;
            $ok = Service::withoutGlobalScopes()
                ->whereKey($id)
                ->whereHas('client', fn ($q) => $q->where('team_id', $teamId))
                ->exists();
            if (! $ok)
            {
                $validator->errors()->add('service_id', __('The selected service is invalid for this team.'));
            }
        });
    }
}
