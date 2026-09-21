<?php

namespace Tests\Feature;

use App\Livewire\OrganizationBoard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_organization(): void
    {
        $this->get(route('organization.index'))->assertRedirect();
    }

    public function test_admin_on_revision_alpha_team_sees_catalog_and_affiliates(): void
    {
        $user = $this->teamUser('admin', 'Magoo');

        $this->actingAs($user)
            ->get(route('organization.index'))
            ->assertOk()
            ->assertSeeLivewire(OrganizationBoard::class)
            ->assertSee('Leticia')
            ->assertSee('Publicidad')
            ->assertSee('Medio')
            ->assertSee('Producto')
            ->assertSee('18:30')
            ->assertSee('09:30')
            ->assertSee('Soporte y migraciones')
            ->assertSee('Emails y WhatsApp')
            ->assertSee('Programar')
            ->assertSee('Aprobar publicaciones')
            ->assertSee('Generar publicidad')
            ->assertSee('Emails y tickets')
            ->assertSee('Subir facturas al sistema')
            ->assertSee('Cierre contable de la semana')
            ->assertSee('Comunicaciones de pago')
            ->assertSee('Plan de publicaciones')
            ->assertSee('Plan de marketing')
            ->assertSee('Llamados a clientes')
            ->assertSee('Prospección')
            ->assertSee('Academia de inglés')
            ->assertSee('Master Mind')
            ->assertSee('Sin cobertura')
            ->assertSee('Guardia 24')
            ->assertSee('14:00 ARG')
            ->assertDontSee('E-commerce conversacional')
            ->assertSee('30%')
            ->assertSee('10%')
            ->assertSee('Ver módulo de afiliados')
            ->assertDontSee('Crear débito automático')
            ->assertDontSee('Enviar débito al banco')
            ->assertDontSee('Recibir respuesta de débito')
            ->assertDontSee('Pago a proveedores');
    }

    public function test_collaborator_named_leticia_sees_her_tasks_in_detail(): void
    {
        $user = $this->teamUser('collaborator', 'Leticia');

        Livewire::actingAs($user)
            ->test(OrganizationBoard::class)
            ->assertSee('Leticia')
            ->assertSee('Ingreso de pagos')
            ->assertSee('Hablar con clientes y seguimiento')
            ->assertSee('Cruzar transferencias')
            ->assertSee('30%')
            ->assertSee('10%');
    }

    public function test_other_team_does_not_see_revision_alpha_catalog(): void
    {
        $user = $this->teamUser('admin', 'Ana', false);
        config(['organization.revision_alpha_team_id' => 99999]);

        $this->actingAs($user)
            ->get(route('organization.index'))
            ->assertOk()
            ->assertDontSee('Ver módulo de afiliados')
            ->assertDontSee('Quién hace qué, cuándo');
    }

    private function teamUser(string $role, string $name, bool $isRevisionAlpha = true): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create([
            'name' => $name,
        ]);
        $team = $user->ownedTeams()->first();
        $user->assignRole($role);
        $user->forceFill(['current_team_id' => $team->id])->save();

        if ($isRevisionAlpha)
        {
            config(['organization.revision_alpha_team_id' => $team->id]);
        }

        return $user->fresh();
    }
}
