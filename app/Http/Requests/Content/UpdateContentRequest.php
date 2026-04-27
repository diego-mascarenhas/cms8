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
        $teamId = $this->user()?->currentTeam?->id;

        $rules = [
            'section_category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(function ($query) use ($contentsModuleId, $teamId)
                {
                    $query->where('module_id', $contentsModuleId)
                        ->where('status', 1)
                        ->where('team_id', $teamId);
                }),
            ],
            'category_id' => 'nullable|exists:categories,id',
            'template' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0|max:255',
            'status' => 'required|integer|in:1,2,3,4',
            'featured' => 'sometimes|boolean',
            'featured_slide' => 'sometimes|boolean',
            'featured_modal' => 'sometimes|boolean',
            // Multi-language fields
            'title_es' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'title_it' => 'nullable|string|max:255',
            'title_pt' => 'nullable|string|max:255',
            'title_fr' => 'nullable|string|max:255',
            'title_de' => 'nullable|string|max:255',
            'subtitle_es' => 'nullable|string|max:255',
            'subtitle_en' => 'nullable|string|max:255',
            'subtitle_it' => 'nullable|string|max:255',
            'subtitle_pt' => 'nullable|string|max:255',
            'subtitle_fr' => 'nullable|string|max:255',
            'subtitle_de' => 'nullable|string|max:255',
            'url_es' => 'nullable|string|max:255',
            'url_en' => 'nullable|string|max:255',
            'url_it' => 'nullable|string|max:255',
            'url_pt' => 'nullable|string|max:255',
            'url_fr' => 'nullable|string|max:255',
            'url_de' => 'nullable|string|max:255',
            'content_es' => 'nullable|string',
            'content_en' => 'nullable|string',
            'content_it' => 'nullable|string',
            'content_pt' => 'nullable|string',
            'content_fr' => 'nullable|string',
            'content_de' => 'nullable|string',
            'seo_title_es' => 'nullable|string|max:255',
            'seo_title_en' => 'nullable|string|max:255',
            'seo_title_it' => 'nullable|string|max:255',
            'seo_title_pt' => 'nullable|string|max:255',
            'seo_title_fr' => 'nullable|string|max:255',
            'seo_title_de' => 'nullable|string|max:255',
            'seo_keywords_es' => 'nullable|string|max:255',
            'seo_keywords_en' => 'nullable|string|max:255',
            'seo_keywords_it' => 'nullable|string|max:255',
            'seo_keywords_pt' => 'nullable|string|max:255',
            'seo_keywords_fr' => 'nullable|string|max:255',
            'seo_keywords_de' => 'nullable|string|max:255',
            'seo_description_es' => 'nullable|string',
            'seo_description_en' => 'nullable|string',
            'seo_description_it' => 'nullable|string',
            'seo_description_pt' => 'nullable|string',
            'seo_description_fr' => 'nullable|string',
            'seo_description_de' => 'nullable|string',
            'cover_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,bmp,svg|max:10240',
            'remove_cover_image' => 'nullable|boolean',
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
