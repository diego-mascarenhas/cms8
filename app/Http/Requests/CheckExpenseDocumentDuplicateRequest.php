<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckExpenseDocumentDuplicateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) auth()->user()->currentTeam->id;

        return [
            'enterprise_id' => [
                'required',
                'integer',
                Rule::exists('enterprises', 'id')->where(fn ($query) => $query
                    ->where('team_id', $teamId)),
            ],
            'document_number' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'enterprise_id' => $this->routeIs('invoice.check-document-duplicate') ? 'cliente' : 'proveedor',
            'document_number' => 'número de comprobante',
        ];
    }
}
