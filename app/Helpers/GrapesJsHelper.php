<?php

namespace App\Helpers;

use App\Models\Template;

class GrapesJsHelper
{
    /**
     * Fix GrapesJS structure after template HTML changes
     */
    public static function fixTemplateStructure(Template $template): bool
    {
        try
        {
            // Get current HTML
            $currentHtml = $template->gjs_data['html'];

            // Create a simple GrapesJS-compatible structure that wraps the existing HTML
            $components = [
                [
                    'tagName' => 'div',
                    'attributes' => [
                        'class' => 'email-wrapper',
                    ],
                    'classes' => [
                        ['name' => 'email-wrapper'],
                    ],
                    'components' => [
                        [
                            'type' => 'text',
                            'content' => $currentHtml,
                            'editable' => true,
                            'removable' => false,
                            'copyable' => false,
                            'draggable' => false,
                        ],
                    ],
                ],
            ];

            // Create basic styles for the wrapper
            $styles = [
                [
                    'selectors' => [
                        ['name' => 'email-wrapper'],
                    ],
                    'style' => [
                        'width' => '100%',
                        'margin' => '0 auto',
                        'font-family' => 'helvetica, arial, verdana, sans-serif',
                    ],
                ],
                [
                    'selectors' => [
                        ['name' => 'email-wrapper', 'type' => 1],
                    ],
                    'style' => [
                        'font-family' => 'helvetica, arial, verdana, sans-serif !important',
                        'line-height' => '1.5',
                    ],
                ],
                [
                    'selectors' => [
                        ['name' => 'email-wrapper', 'type' => 1],
                        ['name' => 'h1,h2,h3,h4,h5,h6,strong', 'type' => 1],
                    ],
                    'style' => [
                        'font-weight' => '600 !important',
                    ],
                ],
                [
                    'selectors' => [
                        ['name' => 'email-wrapper', 'type' => 1],
                        ['name' => 'table[bgcolor="#2A333D"]', 'type' => 1],
                    ],
                    'style' => [
                        'background-color' => '#2A333D !important',
                    ],
                ],
                [
                    'selectors' => [
                        ['name' => 'email-wrapper', 'type' => 1],
                        ['name' => 'table[bgcolor="#2A333D"] span', 'type' => 1],
                    ],
                    'style' => [
                        'color' => '#ffffff !important',
                    ],
                ],
                [
                    'selectors' => [
                        ['name' => 'email-wrapper', 'type' => 1],
                        ['name' => 'table[bgcolor="#2A333D"] a', 'type' => 1],
                    ],
                    'style' => [
                        'color' => '#ffffff !important',
                        'text-decoration' => 'none !important',
                    ],
                ],
            ];

            // Update the template with new GrapesJS structure
            $gjs_data = $template->gjs_data;
            $gjs_data['components'] = json_encode($components);
            $gjs_data['styles'] = json_encode($styles);

            $template->update(['gjs_data' => $gjs_data]);

            return true;
        } catch (\Exception $e)
        {
            \Log::error('GrapesJS Helper Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Fix GrapesJS structure for template by ID
     */
    public static function fixTemplateById(int $templateId): bool
    {
        $template = Template::find($templateId);

        if (! $template)
        {
            return false;
        }

        return self::fixTemplateStructure($template);
    }

    /**
     * Fix all templates GrapesJS structure
     */
    public static function fixAllTemplates(): array
    {
        $results = [];
        $templates = Template::all();

        foreach ($templates as $template)
        {
            $results[$template->id] = [
                'name' => $template->name,
                'success' => self::fixTemplateStructure($template),
            ];
        }

        return $results;
    }
}
