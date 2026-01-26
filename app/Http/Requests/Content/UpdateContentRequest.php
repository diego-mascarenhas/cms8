<?php

namespace App\Http\Requests\Content;

use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Content::class) ?? false;
    }

    public function rules(): array
    {
        $contentsModuleId = Module::where('key', 'contents')->value('id');

        $rules = [
            'section_category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(function ($query) use ($contentsModuleId)
                {
                    $query->where('module_id', $contentsModuleId);
                }),
            ],
            'category_id' => 'nullable|exists:categories,id',
            'template' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0|max:255',
            'status' => 'required|integer|in:1,2,3,4',
            'featured' => 'sometimes|boolean',
            'featured_slide' => 'sometimes|boolean',
            'featured_modal' => 'sometimes|boolean',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'seo_title' => 'nullable|string|max:255',
            'seo_keywords' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'multimedia' => 'nullable|array',
            'multimedia.*.id' => 'required_with:multimedia|exists:multimedia,id',
            'multimedia.*.language' => 'nullable|string|max:2',
            'multimedia.*.type' => 'nullable|integer|min:1|max:10',
            'data' => 'nullable|array',
        ];

        // Add dynamic field validation based on section configuration
        if ($this->has('section_category_id'))
        {
            $section = Category::find($this->input('section_category_id'));
            if ($section)
            {
                $fieldConfigs = $section->contentFieldConfigs()->active()->get();
                foreach ($fieldConfigs as $config)
                {
                    $key = "data.{$config->field_key}";
                    $fieldRules = [];

                    if ($config->required)
                    {
                        $fieldRules[] = 'required';
                    } else
                    {
                        $fieldRules[] = 'nullable';
                    }

                    switch ($config->field_type)
                    {
                        case 'text':
                        case 'url':
                        case 'email':
                            $fieldRules[] = 'string';
                            $fieldRules[] = 'max:255';
                            break;
                        case 'textarea':
                            $fieldRules[] = 'string';
                            break;
                        case 'number':
                            $fieldRules[] = 'numeric';
                            break;
                        case 'date':
                        case 'datetime':
                            $fieldRules[] = 'date';
                            break;
                        case 'checkbox':
                            $fieldRules[] = 'boolean';
                            break;
                        case 'select':
                            if ($config->field_options && is_array($config->field_options) && isset($config->field_options['options']))
                            {
                                $fieldRules[] = 'in:'.implode(',', array_keys($config->field_options['options']));
                            }
                            break;
                    }

                    $rules[$key] = $fieldRules;
                }
            }
        }

        return $rules;
    }
}
