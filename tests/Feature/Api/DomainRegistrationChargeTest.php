<?php

namespace Tests\Feature\Api;

use App\Mail\HostingFailureMail;
use App\Models\Enterprise;
use App\Models\Team;
use App\Models\User;
use App\Services\Domains\EnterpriseDomainCharge;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainRegistrationChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_notifies_webmaster_when_porkbun_fails_after_charge(): void
    {
        $user = $this->admin();
        $enterprise = $this->enterprise($user->currentTeam);
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
            'humano_hosting.error_notify' => 'webmaster@revisionalpha.cloud',
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 9.73,
            ]),
            'https://api.porkbun.com/api/json/v3/domain/create/ejemplo.com' => Http::response([
                'status' => 'ERROR',
                'code' => 'INSUFFICIENT_FUNDS',
                'message' => 'No funds',
            ], 400),
        ]);
        Mail::fake();
        $this->mock(EnterpriseDomainCharge::class, function ($mock)
        {
            $mock->shouldReceive('charge')
                ->once()
                ->andReturn([
                    'payment_intent_id' => 'pi_test_manual_review',
                    'amount_cents' => 1265,
                    'currency' => 'USD',
                ]);
            $mock->shouldReceive('refund')->never();
        });

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/register', [
                'domain' => 'ejemplo.com',
                'enterprise_id' => $enterprise->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No hay saldo suficiente en Porkbun para registrar el dominio.');

        Mail::assertSent(HostingFailureMail::class, function (HostingFailureMail $mail) use ($enterprise): bool
        {
            return $mail->hasTo('webmaster@revisionalpha.cloud')
                && str_contains($mail->technical, 'No hay saldo suficiente en Porkbun')
                && ($mail->context['payment_intent_id'] ?? null) === 'pi_test_manual_review'
                && ($mail->context['enterprise_id'] ?? null) === $enterprise->id
                && ($mail->context['domain'] ?? null) === 'ejemplo.com';
        });
    }

    public function test_register_surfaces_charge_errors_without_calling_porkbun_create(): void
    {
        $user = $this->admin();
        $enterprise = $this->enterprise($user->currentTeam);
        config([
            'services.porkbun.key' => 'pk_test',
            'services.porkbun.secret' => 'sk_test',
            'humano_hosting.domain_markup_percent' => 30,
        ]);
        Http::fake([
            'https://api.porkbun.com/api/json/v3/domain/checkDomain/ejemplo.com' => Http::response([
                'status' => 'SUCCESS',
                'avail' => 'yes',
                'price' => 9.73,
            ]),
        ]);
        $this->mock(EnterpriseDomainCharge::class, function ($mock)
        {
            $mock->shouldReceive('charge')
                ->once()
                ->andThrow(new RuntimeException('La empresa no tiene una tarjeta por defecto. Guardá una tarjeta antes de registrar el dominio.'));
        });

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('domains')->plainTextToken)
            ->postJson('/api/domains/register', [
                'domain' => 'ejemplo.com',
                'enterprise_id' => $enterprise->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La empresa no tiene una tarjeta por defecto. Guardá una tarjeta antes de registrar el dominio.');

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/domain/create/'));
    }

    private function enterprise(Team $team): Enterprise
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        return Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'status_id' => 1,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user;
    }
}
