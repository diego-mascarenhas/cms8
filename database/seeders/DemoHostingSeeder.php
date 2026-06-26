<?php

namespace Database\Seeders;

use App\Enums\ServerStatus;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Team;
use App\Services\WHMService;
use Illuminate\Database\Seeder;

class DemoHostingSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if (! $team)
        {
            $this->command?->warn('⚠️  Demo team not found — skip DemoHostingSeeder.');

            return;
        }

        $host = (string) config('humano_demo.cpanel.host', 'huginn.revisionalpha.cloud');
        $username = (string) config('humano_demo.cpanel.username', 'democpanel');
        $password = (string) config('humano_demo.cpanel.password', '');

        if ($password === '')
        {
            $this->command?->warn('⚠️  DEMO_CPANEL_PASSWORD not set — skip demo cPanel server seed.');

            return;
        }

        $this->command?->info('🖥️  Seeding Demo cPanel server (Huginn)...');

        $team->enableModule('servers');
        $team->enableModule('hosting');

        $server = Server::withoutGlobalScopes()->updateOrCreate(
            [
                'server_url' => $host,
                'team_id' => $team->id,
            ],
            [
                'name' => 'Huginn (Demo cPanel)',
                'ip' => '51.83.76.40',
                'username' => $username,
                'operating_system' => 'Linux',
                'control_panel' => 'cpanel',
                'encrypted_token' => $password,
                'success' => false,
                'status_id' => ServerStatus::Active->value,
                'data' => [
                    'auth_mode' => 'cpanel_user',
                ],
            ],
        );

        $sync = app(WHMService::class)->syncDomainsFromServer($server);

        if ($sync['success'])
        {
            $this->command?->info("✅ Synced {$sync['domains_synced']} hosting account(s) from {$host}");
        } else
        {
            $this->command?->warn('⚠️  Could not sync demo cPanel account: '.($sync['error'] ?? 'unknown error'));

            Domain::updateOrCreate(
                [
                    'domain' => 'demo-cpanelrevisionalpha.net',
                    'server_id' => $server->id,
                ],
                [
                    'username' => $username,
                    'plan' => 'default',
                    'suspended' => false,
                    'is_working' => true,
                    'data' => [],
                ],
            );
        }
    }
}
