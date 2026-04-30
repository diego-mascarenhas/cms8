<?php

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Models\Campaign;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Demo campaigns (same rows as the former static list on /campaigns).
 * Run: php artisan db:seed --class=CampaignSeeder
 */
class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        Team::withoutGlobalScopes()->orderBy('id')->chunkById(50, function ($teams): void
        {
            foreach ($teams as $team)
            {
                $this->seedDemoRowsForTeam((int) $team->id);
            }
        });
    }

    private function seedDemoRowsForTeam(int $teamId): void
    {
        $tz = 'Europe/Madrid';

        Campaign::withoutGlobalScopes()->updateOrCreate(
            [
                'team_id' => $teamId,
                'name' => 'Flujo de bienvenida para docentes',
            ],
            [
                'type' => CampaignType::Sequences->value,
                'status' => CampaignStatus::Active->value,
                'summary' => '7 correos en 150 días',
                'sends_count' => null,
                'opened_rate' => null,
                'clicked_rate' => null,
                'unsubscribed_rate' => null,
                'scheduled_at' => null,
                'sent_at' => null,
            ],
        );

        Campaign::withoutGlobalScopes()->updateOrCreate(
            [
                'team_id' => $teamId,
                'name' => 'Por qué tus alumnos no progresan',
            ],
            [
                'type' => CampaignType::Broadcasts->value,
                'status' => CampaignStatus::Scheduled->value,
                'summary' => 'Programado para 07 mayo 2026 19:00',
                'sends_count' => null,
                'opened_rate' => null,
                'clicked_rate' => null,
                'unsubscribed_rate' => null,
                'scheduled_at' => Carbon::parse('2026-05-07 19:00', $tz)->utc(),
                'sent_at' => null,
            ],
        );

        Campaign::withoutGlobalScopes()->updateOrCreate(
            [
                'team_id' => $teamId,
                'name' => 'Lo que aprendí de los nuevos alumnos',
            ],
            [
                'type' => CampaignType::Broadcasts->value,
                'status' => CampaignStatus::Sent->value,
                'summary' => 'Enviado el 23 abril 2026 19:03',
                'sends_count' => 2381,
                'opened_rate' => 20.00,
                'clicked_rate' => 0.00,
                'unsubscribed_rate' => 0.00,
                'scheduled_at' => null,
                'sent_at' => Carbon::parse('2026-04-23 19:03', $tz)->utc(),
            ],
        );

        Campaign::withoutGlobalScopes()->updateOrCreate(
            [
                'team_id' => $teamId,
                'name' => 'Errores al activar el core',
            ],
            [
                'type' => CampaignType::Broadcasts->value,
                'status' => CampaignStatus::Sent->value,
                'summary' => 'Enviado el 15 abril 2026 18:03',
                'sends_count' => 2399,
                'opened_rate' => 40.00,
                'clicked_rate' => 3.00,
                'unsubscribed_rate' => 0.00,
                'scheduled_at' => null,
                'sent_at' => Carbon::parse('2026-04-15 18:03', $tz)->utc(),
            ],
        );
    }
}
