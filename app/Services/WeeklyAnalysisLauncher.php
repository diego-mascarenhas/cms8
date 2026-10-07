<?php

namespace App\Services;

class WeeklyAnalysisLauncher
{
    public function start(int $teamId): void
    {
        $log = storage_path('logs/weekly-analysis.log');
        $command = sprintf(
            'nohup %s %s strategy:weekly-analysis --team=%d >> %s 2>&1 &',
            escapeshellarg($this->phpBinary()),
            escapeshellarg(base_path('artisan')),
            $teamId,
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
