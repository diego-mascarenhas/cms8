<?php

namespace Tests\Unit\Billing;

use App\Models\Team;
use App\Models\User;
use App\Services\Billing\TeamBillingDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamBillingBusinessNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_stripe_metadata_over_customer_name(): void
    {
        $team = $this->team('Equipo');
        $customer = $this->customer([
            'name' => 'Cliente Stripe',
            'business_name' => 'ACME S.L.',
        ]);

        $this->assertSame('ACME S.L.', $this->service()->resolveBusinessName($team, $customer, 'Diego'));
    }

    public function test_skips_empty_metadata_and_uses_stripe_customer_name(): void
    {
        $team = $this->team('Equipo');
        $customer = $this->customer([
            'name' => 'Idoneo Projects S.L.',
            'business_name' => '',
            'company_name' => '',
        ]);

        $this->assertSame(
            'Idoneo Projects S.L.',
            $this->service()->resolveBusinessName($team, $customer, 'Diego Mascarenhas'),
        );
    }

    public function test_does_not_copy_individual_name_into_business_name(): void
    {
        $team = $this->team('Equipo');
        $customer = $this->customer([
            'name' => 'Diego Mascarenhas',
            'business_name' => '',
        ]);

        $this->assertSame('Equipo', $this->service()->resolveBusinessName($team, $customer, 'Diego Mascarenhas'));
    }

    public function test_falls_back_to_team_business_config(): void
    {
        $team = $this->team('Equipo');
        $team->setSetting('business_config', ['business_name' => 'Marca Configurada'], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $customer = $this->customer([
            'name' => 'Diego Mascarenhas',
            'business_name' => '',
        ]);

        $this->assertSame(
            'Marca Configurada',
            $this->service()->resolveBusinessName($team->fresh(), $customer, 'Diego Mascarenhas'),
        );
    }

    private function service(): TeamBillingDataService
    {
        return app(TeamBillingDataService::class);
    }

    private function team(string $name): Team
    {
        $owner = User::factory()->create();

        return Team::factory()->create([
            'user_id' => $owner->id,
            'name' => $name,
        ]);
    }

    /**
     * @param  array{name?: string, business_name?: string, company_name?: string}  $data
     */
    private function customer(array $data): object
    {
        return (object) [
            'name' => $data['name'] ?? '',
            'metadata' => (object) [
                'business_name' => $data['business_name'] ?? '',
                'company_name' => $data['company_name'] ?? '',
            ],
            'collected_information' => (object) [
                'business_name' => $data['collected_business_name'] ?? '',
            ],
        ];
    }
}
