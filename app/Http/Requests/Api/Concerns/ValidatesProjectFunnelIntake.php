<?php

namespace App\Http\Requests\Api\Concerns;

trait ValidatesProjectFunnelIntake
{
    /**
     * Optional intake fields shared by the public quote funnel and estimator.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function funnelIntakeRules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:40'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'approx_users' => ['nullable', 'string', 'max:80'],
            'integrations' => ['nullable', 'string', 'max:1000'],
            'needed_by' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function funnelIntakeMessages(): array
    {
        return [
            'phone.max' => __('Phone may not be greater than 40 characters.'),
            'location.max' => __('Location may not be greater than 255 characters.'),
        ];
    }
}
