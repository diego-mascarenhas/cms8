<?php

namespace Tests\Unit;

use App\Helpers\Helpers;
use Tests\TestCase;

class HelpersLogoAssetTest extends TestCase
{
    public function test_logo_asset_for_style_maps_theme_to_variant(): void
    {
        $this->assertSame(
            Helpers::logoAsset('light'),
            Helpers::logoAssetForStyle('light'),
        );

        $this->assertSame(
            Helpers::logoAsset('dark'),
            Helpers::logoAssetForStyle('dark'),
        );
    }

    public function test_logo_light_and_dark_paths_resolve(): void
    {
        $this->assertStringContainsString('logo-light.svg', Helpers::logoAsset('light'));
        $this->assertStringContainsString('logo-dark.svg', Helpers::logoAsset('dark'));
    }

    public function test_logo_theme_data_img_is_relative_to_assets_img(): void
    {
        $this->assertSame('../logo-light.svg', Helpers::logoThemeDataImg('light'));
        $this->assertSame('../logo-dark.svg', Helpers::logoThemeDataImg('dark'));
    }
}
