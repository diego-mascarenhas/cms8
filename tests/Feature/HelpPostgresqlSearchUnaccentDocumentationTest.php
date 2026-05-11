<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpPostgresqlSearchUnaccentDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_postgresql_unaccent_page_is_public(): void
    {
        $response = $this->get(route('help.postgresql-search-unaccent'));

        $response->assertOk();
        $response->assertSee('CREATE EXTENSION IF NOT EXISTS unaccent', false);
        $response->assertSee('SearchNormalizer', false);
    }
}
