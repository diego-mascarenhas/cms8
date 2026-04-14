<?php

namespace Tests\Feature;

use App\Models\ContentFieldConfig;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DynamicContentFieldsUrlInputTest extends TestCase
{
    public function test_url_field_type_renders_text_input_not_html_url_type(): void
    {
        view()->share('errors', new ViewErrorBag);

        $config = ContentFieldConfig::make([
            'field_key' => 'image_url',
            'field_type' => 'url',
            'field_label' => 'URL de imagen',
            'required' => false,
        ]);

        $html = Blade::render(
            '<x-dynamic-content-fields :fieldConfigs="$configs" />',
            ['configs' => collect([$config])],
        );

        $this->assertStringContainsString('name="data[image_url]"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringNotContainsString('type="url"', $html);
    }
}
