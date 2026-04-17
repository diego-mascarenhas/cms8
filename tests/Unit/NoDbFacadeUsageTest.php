<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NoDbFacadeUsageTest extends TestCase
{
    public function test_runtime_layers_and_commands_do_not_use_db_facade_static_calls(): void
    {
        $paths = [
            '/../../app/Http',
            '/../../app/Services',
            '/../../app/Models',
            '/../../app/Actions',
            '/../../app/Console/Commands',
        ];

        $filesWithFacadeUsage = [];

        foreach ($paths as $relativePath)
        {
            $directory = realpath(__DIR__.$relativePath);

            if (! $directory || ! is_dir($directory))
            {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file)
            {
                if (! $file->isFile() || $file->getExtension() !== 'php')
                {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if ($contents !== false && str_contains($contents, 'DB::'))
                {
                    $filesWithFacadeUsage[] = str_replace(realpath(__DIR__.'/../../').'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $filesWithFacadeUsage, 'DB facade static usage found in: '.implode(', ', $filesWithFacadeUsage));
    }
}
