<?php

namespace Tests\Feature;

use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CountrySelectComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        view()->share('errors', new ViewErrorBag);
    }

    public function test_country_select_by_id_lists_all_seeded_countries(): void
    {
        $this->seed(CountrySeeder::class);

        $html = Blade::render('<x-country-select name="country" :value="32" />');

        $this->assertStringContainsString('Argentina', $html);
        $this->assertStringContainsString('Zimbabue', $html);
        $this->assertStringContainsString('value="32"', $html);
    }

    public function test_country_select_by_code_uses_iso_codes_for_stripe(): void
    {
        $this->seed(CountrySeeder::class);

        $html = Blade::render('<x-country-select name="country" value-key="code" value="AR" />');

        $this->assertStringContainsString('value="AR"', $html);
        $this->assertStringContainsString('Argentina', $html);
        $this->assertStringContainsString('value="ZW"', $html);
        $this->assertStringContainsString('Zimbabue', $html);
    }
}
