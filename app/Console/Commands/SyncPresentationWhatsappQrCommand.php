<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncPresentationWhatsappQrCommand extends Command
{
    protected $signature = 'humano:sync-presentation-whatsapp-qr
                            {--team= : Team ID used for team_id query on the WhatsApp service}
                            {--refresh : Call /refresh on the service before downloading the QR}';

    protected $description = 'Download WhatsApp QR PNG for the marketing presentation (chat-contactos-modulos slide)';

    public function handle(): int
    {
        if (config('whatsapp.driver') !== 'local')
        {
            $this->error('WHATSAPP_DRIVER must be "local".');

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) config('whatsapp.local.base_url', ''), '/');
        if ($baseUrl === '')
        {
            $this->error('WHATSAPP_LOCAL_BASE_URL is not set.');

            return self::FAILURE;
        }

        $teamId = $this->resolveTeamId();
        if ($this->option('refresh'))
        {
            $this->refreshQr($baseUrl, $teamId);
        }

        $qrUrl = $baseUrl.'/qr.png';
        if ($teamId !== null)
        {
            $qrUrl .= (str_contains($qrUrl, '?') ? '&' : '?').'team_id='.$teamId;
        }

        $this->info('Fetching '.$qrUrl);

        try
        {
            $response = Http::timeout(15)->connectTimeout(5)->get($qrUrl);
        } catch (\Throwable $e)
        {
            $this->error('Could not reach WhatsApp service: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful() || strlen($response->body()) < 100)
        {
            $this->error('QR not available (HTTP '.$response->status().'). Run whatsapp:service refresh or wait for the service to generate a code.');

            return self::FAILURE;
        }

        $directory = public_path('homes/humano/img/presentations');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory))
        {
            $this->error('Could not create directory: '.$directory);

            return self::FAILURE;
        }

        $path = $directory.'/whatsapp-qr.png';
        file_put_contents($path, $response->body());

        $this->info('Saved presentation QR to '.$path);

        $embedded = $this->embedQrInPresentationHtml($response->body());
        if ($embedded > 0)
        {
            $this->info('Embedded QR in '.$embedded.' presentation HTML file(s).');
        }

        return self::SUCCESS;
    }

    private function embedQrInPresentationHtml(string $pngBytes): int
    {
        $dataUri = 'data:image/png;base64,'.base64_encode($pngBytes);
        $files = [
            public_path('homes/humano/presentations/chat-contactos-modulos.html'),
            public_path('homes/humano/presentations/snippets/chat-whatsapp-panel.html'),
        ];

        $updated = 0;
        foreach ($files as $file)
        {
            if (! is_file($file))
            {
                continue;
            }

            $contents = file_get_contents($file);
            if ($contents === false)
            {
                continue;
            }

            $replaced = preg_replace(
                '/src="(?:data:image\/png;base64,[^"]+|[^"]*whatsapp-qr\.png[^"]*)"/',
                'src="'.$dataUri.'"',
                $contents,
                count: $count,
            );

            if ($count > 0 && $replaced !== null && file_put_contents($file, $replaced) !== false)
            {
                $updated++;
            }
        }

        return $updated;
    }

    private function resolveTeamId(): ?int
    {
        $teamOption = $this->option('team');
        if ($teamOption !== null && $teamOption !== '')
        {
            return (int) $teamOption;
        }

        return Team::query()->orderBy('id')->value('id');
    }

    private function refreshQr(string $baseUrl, ?int $teamId): void
    {
        $refreshUrl = $baseUrl.'/refresh';
        if ($teamId !== null)
        {
            $refreshUrl .= (str_contains($refreshUrl, '?') ? '&' : '?').'team_id='.$teamId;
        }

        try
        {
            Http::timeout(15)->get($refreshUrl);
            $this->line('Refresh requested.');
        } catch (\Throwable $e)
        {
            $this->warn('Refresh failed: '.$e->getMessage());
        }
    }
}
