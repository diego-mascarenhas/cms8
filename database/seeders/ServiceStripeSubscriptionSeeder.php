<?php

namespace Database\Seeders;

use App\Models\Enterprise;
use App\Models\PaymentSubscription;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ServiceStripeSubscriptionSeeder extends Seeder
{
    /**
     * Minimal seeder to test Service ↔ PaymentSubscription.
     * Creates one PaymentSubscription (provider: local) and one Service linked to it.
     */
    public function run(): void
    {
        $team = Team::first();
        if (! $team)
        {
            $this->command->warn('No team found. Run a seeder that creates teams first.');

            return;
        }

        $enterprise = Enterprise::where('team_id', $team->id)->first();
        if (! $enterprise)
        {
            $this->command->warn('No enterprise found for team. Create an enterprise first.');

            return;
        }

        $serviceType = ServiceType::first();
        if (! $serviceType)
        {
            $this->command->warn('No service type found. Run service types seeder first.');

            return;
        }

        // Global option (team_id = null) so all teams see at least "Facturación local"
        PaymentSubscription::firstOrCreate(
            [
                'team_id' => null,
                'provider' => 'local',
                'name' => 'Facturación local',
            ],
            [
                'status' => 'active',
                'data' => ['interval' => 'month'],
            ],
        );

        $sub = PaymentSubscription::firstOrCreate(
            [
                'team_id' => $team->id,
                'external_id' => 'seed_sub_'.md5($team->id.'_service_payment'),
            ],
            [
                'provider' => 'local',
                'status' => 'active',
                'name' => 'Plan prueba (Seeder)',
                'data' => ['interval' => 'month', 'currency' => 'eur'],
            ],
        );

        Service::withoutGlobalScopes()->firstOrCreate(
            [
                'enterprise_id' => $enterprise->id,
                'subscription_id' => $sub->id,
                'service_type_id' => $serviceType->id,
                'operation' => 'sell',
                'description' => 'Servicio de prueba vinculado a suscripción de pago',
            ],
            [
                'status' => 4,
                'responsible_id' => null,
            ],
        );

        $this->command->info('ServiceStripeSubscriptionSeeder: PaymentSubscription and Service created/linked.');
    }
}
