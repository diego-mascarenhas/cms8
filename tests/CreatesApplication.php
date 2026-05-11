<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $this->enforceSqliteInMemoryForTests();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * PHPUnit should set DB_* via phpunit.xml, but Dotenv can still leave MySQL from .env as default
     * when env inheritance differs by host or runner. Setting these before bootstrap guarantees
     * RefreshDatabase never touches your development database.
     */
    protected function enforceSqliteInMemoryForTests(): void
    {
        if (filter_var(getenv('ALLOW_UNSAFE_TEST_DB') ?: ($_ENV['ALLOW_UNSAFE_TEST_DB'] ?? ''), FILTER_VALIDATE_BOOL))
        {
            return;
        }

        foreach (
            [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
            ] as $key => $value
        ) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
