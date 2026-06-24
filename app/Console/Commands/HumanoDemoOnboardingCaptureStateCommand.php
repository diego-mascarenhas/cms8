<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Support\DemoTeam;
use Illuminate\Console\Command;

class HumanoDemoOnboardingCaptureStateCommand extends Command
{
    public const SETTING_SIMULATE_WHATSAPP_CONNECTED = 'onboarding_capture_simulate_whatsapp_connected';

    protected $signature = 'humano:demo-onboarding-capture-state
                            {state : reset or complete}
                            {--team= : Demo team ID (default: first team named Demo)}';

    protected $description = 'Toggle Demo team onboarding banner state for tutorial video capture (local only)';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']))
        {
            $this->error('This command is only available in the local and testing environments.');

            return self::FAILURE;
        }

        $team = $this->resolveDemoTeam();
        if ($team === null)
        {
            $this->error('Demo team not found.');

            return self::FAILURE;
        }

        $state = strtolower((string) $this->argument('state'));

        if ($state === 'reset')
        {
            $this->resetOnboardingState($team);
            $this->info('Demo onboarding reset: business config cleared, WhatsApp banner CTA enabled.');

            return self::SUCCESS;
        }

        if ($state === 'complete')
        {
            $this->completeOnboardingState($team);
            $this->info('Demo onboarding complete: business configured, WhatsApp simulated as connected.');

            return self::SUCCESS;
        }

        $this->error('State must be "reset" or "complete".');

        return self::FAILURE;
    }

    private function resolveDemoTeam(): ?Team
    {
        $teamOption = $this->option('team');
        if ($teamOption !== null && $teamOption !== '')
        {
            $team = Team::query()->find((int) $teamOption);

            return $team !== null && DemoTeam::isDemoTeam($team) ? $team : null;
        }

        return Team::query()->where('name', DemoTeam::TEAM_NAME)->orderBy('id')->first();
    }

    private function resetOnboardingState(Team $team): void
    {
        $team->setSetting('business_config', [], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $team->setSetting(self::SETTING_SIMULATE_WHATSAPP_CONNECTED, false, [
            'type' => 'boolean',
            'group' => 'onboarding-capture',
        ]);
    }

    private function completeOnboardingState(Team $team): void
    {
        $team->setSetting('business_config', [
            'business_name' => 'Humano Demo',
            'business_industry' => 'Software',
            'business_description' => 'Plataforma de gestión empresarial para equipos comerciales.',
            'business_tagline' => 'Gestión simple para tu negocio',
            'business_phone' => '+34999000999',
            'business_whatsapp' => '+34999000999',
            'business_email' => 'demo@humano.app',
            'business_website' => 'https://humano.app',
            'first_name' => 'Admin',
            'last_name' => 'Demo',
            'business_challenge' => 'Mejorar seguimiento comercial y respuesta a clientes.',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $team->setSetting('whatsapp_from', '34999000999', [
            'type' => 'string',
            'group' => 'whatsapp',
        ]);

        $team->setSetting(self::SETTING_SIMULATE_WHATSAPP_CONNECTED, true, [
            'type' => 'boolean',
            'group' => 'onboarding-capture',
        ]);

        $team->enableModule('list60');
    }
}
