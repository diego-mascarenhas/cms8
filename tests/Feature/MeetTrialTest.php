<?php

namespace Tests\Feature;

use Tests\TestCase;

class MeetTrialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.revisionalpha.meet_url' => 'https://revisionalpha.test/meet']);
    }

    public function test_cms_meet_url_redirects_to_revision_alpha(): void
    {
        $this->get(route('meet.trial'))
            ->assertRedirect('https://revisionalpha.test/meet');
    }

    public function test_custom_room_slug_is_canonicalized_on_revision_alpha(): void
    {
        $this->get(route('meet.trial', ['room' => 'Sala-Equipo', 'nombre' => 'Ana']))
            ->assertRedirect('https://revisionalpha.test/meet/sala-equipo?nombre=Ana');
    }

    public function test_unsafe_room_slug_falls_back_to_the_revision_alpha_room(): void
    {
        $this->get('/meet/'.rawurlencode('sala privada'))
            ->assertRedirect('https://revisionalpha.test/meet');

        $this->get('/meet/ab')
            ->assertRedirect('https://revisionalpha.test/meet');
    }
}
