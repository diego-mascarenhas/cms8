<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class WhatsAppServiceCommand extends Command
{
    protected $signature = 'whatsapp:service
                            {action=status : status|refresh|restart|start}';

    protected $description = 'Manage the local Node.js WhatsApp service (status, refresh QR, restart/start via PM2)';

    public function handle(): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['status', 'refresh', 'restart', 'start'], true))
        {
            $this->error("Action must be: status, refresh, restart, or start. Got: {$action}");

            return self::FAILURE;
        }

        if (config('whatsapp.driver') !== 'local')
        {
            $this->warn('WhatsApp driver is not "local". Set WHATSAPP_DRIVER=local in .env to use this command.');
            if ($action !== 'restart')
            {
                return self::FAILURE;
            }
        }

        switch ($action)
        {
            case 'status':
                return $this->actionStatus();
            case 'refresh':
                return $this->actionRefresh();
            case 'restart':
                return $this->actionRestart();
            case 'start':
                return $this->actionStart();
        }

        return self::FAILURE;
    }

    private function actionStatus(): int
    {
        $baseUrl = rtrim(config('whatsapp.local.base_url', ''), '/');
        if ($baseUrl === '')
        {
            $this->error('WHATSAPP_LOCAL_BASE_URL is not set in .env');

            return self::FAILURE;
        }

        try
        {
            $response = Http::timeout(5)->get($baseUrl.'/status');
        } catch (\Throwable $e)
        {
            $this->error('Cannot reach the WhatsApp service: '.$e->getMessage());
            $this->line('Make sure the Node.js service is running (e.g. in whatsapp-service: npm start or pm2 start server.js --name whatsapp-service).');

            return self::FAILURE;
        }

        if (! $response->successful())
        {
            $this->error('Service returned HTTP '.$response->status());

            return self::FAILURE;
        }

        $data = $response->json();
        $status = $data['status'] ?? 'unknown';
        $number = $data['number'] ?? null;

        $this->table(
            ['Status', 'Linked number'],
            [[$status, $number ? '+'.$number : '—']],
        );

        return self::SUCCESS;
    }

    private function actionRefresh(): int
    {
        $baseUrl = rtrim(config('whatsapp.local.base_url', ''), '/');
        if ($baseUrl === '')
        {
            $this->error('WHATSAPP_LOCAL_BASE_URL is not set in .env');

            return self::FAILURE;
        }

        try
        {
            $response = Http::timeout(10)->get($baseUrl.'/refresh');
        } catch (\Throwable $e)
        {
            $this->error('Cannot reach the WhatsApp service: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful())
        {
            $this->error('Service returned HTTP '.$response->status());

            return self::FAILURE;
        }

        $this->info('Refresh requested. A new QR code should appear in a few seconds (check the WhatsApp connection page or sidebar).');

        return self::SUCCESS;
    }

    private function actionRestart(): int
    {
        $name = 'whatsapp-service';
        $cwd = base_path('whatsapp-service');

        if (! is_dir($cwd))
        {
            $this->error('Directory whatsapp-service not found at: '.$cwd);

            return self::FAILURE;
        }

        if (! $this->confirm('Restart the WhatsApp Node.js service (PM2)?', true))
        {
            return self::SUCCESS;
        }

        $process = new Process(['npx', 'pm2', 'restart', $name], base_path(), null, null, 30);
        $process->run();

        if (! $process->isSuccessful())
        {
            $this->error('PM2 restart failed:');
            $this->line($process->getErrorOutput() ?: $process->getOutput());
            $this->line('');
            $this->line('Ensure PM2 is available (npm install -g pm2 or npx pm2) and the app was started with: pm2 start server.js --name whatsapp-service');

            return self::FAILURE;
        }

        $this->info('WhatsApp service restart requested via PM2.');
        $this->line($process->getOutput());

        return self::SUCCESS;
    }

    private function actionStart(): int
    {
        $name = 'whatsapp-service';
        $cwd = base_path('whatsapp-service');

        if (! is_dir($cwd))
        {
            $this->error('Directory whatsapp-service not found at: '.$cwd);

            return self::FAILURE;
        }

        $script = $cwd.DIRECTORY_SEPARATOR.'server.js';
        if (! is_file($script))
        {
            $this->error('server.js not found in whatsapp-service');

            return self::FAILURE;
        }

        $process = new Process(['npx', 'pm2', 'start', 'server.js', '--name', $name], $cwd, null, null, 30);
        $process->run();

        if (! $process->isSuccessful())
        {
            $this->error('PM2 start failed:');
            $this->line($process->getErrorOutput() ?: $process->getOutput());
            $this->line('');
            $this->line('If the app is already running, use: php artisan whatsapp:service restart');

            return self::FAILURE;
        }

        $this->info('WhatsApp service started via PM2.');
        $this->line($process->getOutput());

        return self::SUCCESS;
    }
}
