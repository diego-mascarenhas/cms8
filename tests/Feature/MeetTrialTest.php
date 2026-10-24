<?php

namespace Tests\Feature;

use Tests\TestCase;

class MeetTrialTest extends TestCase
{
    public function test_guest_can_open_the_public_meet_trial(): void
    {
        $shareUrl = route('meet.trial', ['room' => 'idoneo-cms8-prueba']);

        $this->get(route('meet.trial'))
            ->assertOk()
            ->assertSee('Sala de prueba', false)
            ->assertSee('sin contraseña', false)
            ->assertSee('https://meet.jit.si/external_api.js', false)
            ->assertSee('idoneo-cms8-prueba', false)
            ->assertSee(str_replace('/', '\\/', $shareUrl), false)
            ->assertSee('https://meet.jit.si/idoneo-cms8-prueba', false)
            ->assertDontSee('jwt', false);
    }

    public function test_custom_room_slug_is_canonicalized(): void
    {
        $this->get(route('meet.trial', ['room' => 'Sala-Equipo', 'nombre' => 'Ana']))
            ->assertRedirect(route('meet.trial', ['room' => 'sala-equipo', 'nombre' => 'Ana']));

        $this->get(route('meet.trial', ['room' => 'sala-equipo', 'nombre' => 'Ana']))
            ->assertOk()
            ->assertSee('sala-equipo', false)
            ->assertSee('value="Ana"', false)
            ->assertSee('https://meet.jit.si/sala-equipo', false);
    }

    public function test_unsafe_room_slug_falls_back_to_the_public_room(): void
    {
        $this->get('/meet/'.rawurlencode('sala privada'))
            ->assertRedirect(route('meet.trial'));

        $this->get('/meet/ab')
            ->assertRedirect(route('meet.trial'));
    }

    public function test_display_name_is_escaped(): void
    {
        $this->get(route('meet.trial', ['nombre' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
