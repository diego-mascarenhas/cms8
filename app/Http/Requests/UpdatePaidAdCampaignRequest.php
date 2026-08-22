<?php

namespace App\Http\Requests;

use App\Enums\AdCreativeFormat;
use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Services\PaidAds\PaidAdCampaignApiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePaidAdCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller against the resolved model.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $teamId = $this->user()->currentTeam->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['required', new Enum(PaidAdObjective::class)],
            'status' => ['nullable', new Enum(PaidAdCampaignStatus::class)],
            'budget_type' => ['required', Rule::in(['daily', 'lifetime'])],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::in(PaidAdCampaignApiService::CURRENCIES)],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'creative' => ['nullable', 'array'],
            'creative.headline' => ['nullable', 'string', 'max:255'],
            'creative.body' => ['nullable', 'string', 'max:2000'],
            'creative.url' => ['nullable', 'url', 'max:2000'],
            'creative.assets' => ['nullable', 'array'],
            'creative.assets.*.format' => ['required', new Enum(AdCreativeFormat::class)],
            'creative.assets.*.path' => ['required', 'string', 'max:255'],
            'creative.assets.*.url' => ['nullable', 'string', 'max:2000'],
            'creative.assets.*.width' => ['nullable', 'integer', 'min:1'],
            'creative.assets.*.height' => ['nullable', 'integer', 'min:1'],
            'creative.assets.*.original_name' => ['nullable', 'string', 'max:255'],
            'targeting' => ['nullable', 'array'],
            'targeting.locations' => ['nullable', 'string', 'max:1000'],
            'targeting.age_min' => ['nullable', 'integer', 'min:13', 'max:99'],
            'targeting.age_max' => ['nullable', 'integer', 'min:13', 'max:99'],
            'targeting.interests' => ['nullable', 'string', 'max:1000'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['integer', Rule::exists('ad_platform_connections', 'id')->where('team_id', $teamId)],
            'audiences' => ['nullable', 'array'],
            'audiences.*' => ['integer', Rule::exists('paid_ad_audiences', 'id')->where('team_id', $teamId)],
        ];
    }
}
