<?php

namespace App\Helpers;

use App\Models\Template;
use Illuminate\Support\Facades\Http;

class TemplateImportHelper
{
    /**
     * Import template HTML from a URL and create a new Template.
     *
     * @return Template|null
     */
    public static function importTemplateFromUrl(string $url, ?string $name = null)
    {
        try
        {
            $response = Http::get($url);
            if ($response->successful())
            {
                $html = $response->body();
                $templateName = $name ?? 'Imported Template '.now()->format('Ymd_His');
                $gjsData = [
                    'html' => $html,
                    'css' => '',
                    'components' => '[]',
                    'styles' => '[]',
                ];
                $template = Template::create([
                    'name' => $templateName,
                    'gjs_data' => $gjsData, // Guardar como array, sin json_encode
                ]);

                return $template;
            }
        } catch (\Exception $e)
        {
            // Log error if needed
        }

        return null;
    }
}
