<?php

namespace App\Support\DatabaseSync;

class PostgresCliBinaryResolver
{
    public static function resolve(string $binary): ?string
    {
        foreach (self::searchedPathsFor($binary) as $path)
        {
            if (is_file($path) && is_executable($path))
            {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function searchedPathsFor(string $binary): array
    {
        $binary = basename($binary);
        $paths = [];

        foreach (self::directories() as $directory)
        {
            $paths[] = rtrim($directory, '/').'/'.$binary;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private static function directories(): array
    {
        $directories = [];
        $configured = config('database.pg_bin');

        if (is_string($configured) && trim($configured) !== '')
        {
            $directories[] = trim($configured);
        }

        $path = getenv('PATH');
        if (is_string($path) && $path !== '')
        {
            foreach (explode(PATH_SEPARATOR, $path) as $directory)
            {
                if ($directory !== '')
                {
                    $directories[] = $directory;
                }
            }
        }

        return array_merge($directories, [
            '/opt/homebrew/opt/libpq/bin',
            '/usr/local/opt/libpq/bin',
            '/opt/homebrew/bin',
            '/usr/local/bin',
            '/usr/bin',
            '/Applications/Postgres.app/Contents/Versions/latest/bin',
        ], self::postgresAppVersionBins(), self::herdBins());
    }

    /**
     * @return list<string>
     */
    private static function postgresAppVersionBins(): array
    {
        $matches = glob('/Applications/Postgres.app/Contents/Versions/*/bin');

        if (! is_array($matches))
        {
            return [];
        }

        return array_values(array_filter($matches, 'is_string'));
    }

    /**
     * @return list<string>
     */
    private static function herdBins(): array
    {
        $home = getenv('HOME');

        if (! is_string($home) || $home === '')
        {
            return [];
        }

        return [$home.'/Library/Application Support/Herd/bin'];
    }
}
