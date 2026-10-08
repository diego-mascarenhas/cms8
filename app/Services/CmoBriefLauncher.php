<?php

namespace App\Services;

class CmoBriefLauncher
{
    public function start(int $teamId, int $year): void
    {
        $log = storage_path('logs/cmo-brief.log');
        $command = sprintf(
            'nohup %s %s strategy:cmo-brief --team=%d --year=%d >> %s 2>&1 &',
            escapeshellarg($this->phpBinary()),
            escapeshellarg(base_path('artisan')),
            $teamId,
            $year,
            escapeshellarg($log),
        );

        exec($command);
    }

    private function phpBinary(): string
    {
        $binary = PHP_BINARY;

        if (str_ends_with($binary, '-fpm'))
        {
            $cli = substr($binary, 0, -4);

            if (is_executable($cli))
            {
                return $cli;
            }
        }

        if (is_executable($binary) && ! str_contains($binary, 'fpm'))
        {
            return $binary;
        }

        $fallback = PHP_BINDIR.'/php';

        return is_executable($fallback) ? $fallback : $binary;
    }
}
