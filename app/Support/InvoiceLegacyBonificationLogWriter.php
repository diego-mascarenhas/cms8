<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class InvoiceLegacyBonificationLogWriter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function write(array $payload, ?string $path = null): string
    {
        $directory = storage_path('logs/invoice-corrections');
        File::ensureDirectoryExists($directory);

        $path ??= $directory.'/legacy-bonification-'.now()->format('Y-m-d_His').'.json';

        File::put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );

        return $path;
    }
}
