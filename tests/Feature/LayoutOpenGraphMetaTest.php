<?php

namespace Tests\Feature;

use Tests\TestCase;

class LayoutOpenGraphMetaTest extends TestCase
{
    public function test_login_page_renders_open_graph_title_from_section_not_blade_yield(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('<title>'.__('auth.login.title').' | '.config('variables.templateName').'</title>', false);
        $response->assertSee('property="og:title" content="'.__('auth.login.title').' | '.config('variables.templateName').'"', false);
        $response->assertDontSee('@yield(\'title\')', false);
    }
}
