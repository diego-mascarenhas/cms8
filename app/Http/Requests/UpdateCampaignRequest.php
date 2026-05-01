<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\SubscriptionProduct;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = auth()->user()?->current_team_id;

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'send_time_zone' => ['nullable', 'timezone:all'],
            'sequence_exclusions_present' => ['nullable'],
        ];

        if ($this->boolean('sequence_exclusions_present'))
        {
            $rules['exclude_offer_refs'] = ['nullable', 'array'];
            $rules['exclude_offer_refs.*'] = ['required', 'string', 'regex:/^(product|subscription):\d+$/'];
            $rules['exclude_content_ids'] = ['nullable', 'array'];
            $rules['exclude_content_ids.*'] = [
                'integer',
                Rule::exists('contents', 'id')->where(fn ($q) => $q->where('team_id', $teamId)),
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('sequence_exclusions_present'))
        {
            $this->merge([
                'exclude_offer_refs' => $this->input('exclude_offer_refs', []),
                'exclude_content_ids' => $this->input('exclude_content_ids', []),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void
        {
            if (! $this->boolean('sequence_exclusions_present'))
            {
                return;
            }

            $refs = $this->input('exclude_offer_refs', []);
            if (! is_array($refs))
            {
                return;
            }

            foreach ($refs as $idx => $ref)
            {
                if (! is_string($ref) || ! preg_match('/^(product|subscription):(\d+)$/', $ref, $m))
                {
                    continue;
                }

                $type = $m[1];
                $id = (int) $m[2];

                if ($type === 'product')
                {
                    if (! Product::query()->whereKey($id)->exists())
                    {
                        $v->errors()->add(
                            "exclude_offer_refs.{$idx}",
                            __('El producto seleccionado no existe o no está disponible.'),
                        );
                    }

                    continue;
                }

                if ($type === 'subscription')
                {
                    if (! SubscriptionProduct::query()->whereKey($id)->where('active', true)->exists())
                    {
                        $v->errors()->add(
                            "exclude_offer_refs.{$idx}",
                            __('El plan o suscripción seleccionado no existe o no está activo.'),
                        );
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Título interno'),
            'send_time_zone' => __('Zona horaria predeterminada'),
            'exclude_offer_refs' => __('Exclusiones por producto o suscripción'),
            'exclude_content_ids' => __('Exclusiones por contenido'),
        ];
    }
}
