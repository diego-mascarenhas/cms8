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

    public function test_budget_logo_falls_back_to_light_when_unset(): void
    {
        config(['variables.logo.budget_path' => '']);

        $this->assertSame(Helpers::logoAsset('light'), Helpers::budgetLogoAsset());
    }

    public function test_budget_logo_uses_absolute_url_from_env(): void
    {
        config(['variables.logo.budget_path' => 'https://mi.humano.app/assets/logo-dark.svg']);

        $this->assertSame('https://mi.humano.app/assets/logo-dark.svg', Helpers::budgetLogoAsset());
    }

    public function test_budget_logo_falls_back_when_local_file_is_missing(): void
    {
        config(['variables.logo.budget_path' => 'assets/missing-budget-logo.svg']);

        $this->assertSame(Helpers::logoAsset('light'), Helpers::budgetLogoAsset());
    }
}
