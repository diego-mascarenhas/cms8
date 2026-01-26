<?php

namespace App\Http\Requests\Multimedia;

use App\Models\Multimedia;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGalleryOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Multimedia::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gallery_tag_id' => 'required|exists:tags,id',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:multimedia,id',
            'items.*.order' => 'required|integer|min:0',
        ];
    }
}
