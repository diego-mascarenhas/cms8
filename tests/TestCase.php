<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Prevent RefreshDatabase and similar traits from ever targeting MySQL/Postgres
     * when phpunit env vars are misapplied (e.g. missing phpunit.xml). See phpunit.xml DB_*.
     * Override with env ALLOW_UNSAFE_TEST_DB=1 only for intentional DB clones (not your main dev DB).
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTestsUseIsolatedDatabase();
        config(['daily_performance_insight.use_llm' => false]);
    }

    protected function assertTestsUseIsolatedDatabase(): void
    {
        if (filter_var(getenv('ALLOW_UNSAFE_TEST_DB') ?: ($_ENV['ALLOW_UNSAFE_TEST_DB'] ?? ''), FILTER_VALIDATE_BOOL))
        {
            return;
        }

        if (! $this->app->environment('testing'))
        {
            return;
        }

        $name = (string) config('database.default');
        $config = config("database.connections.{$name}", []);
        $driver = (string) ($config['driver'] ?? '');
        $database = (string) ($config['database'] ?? '');

        if ($driver !== 'sqlite')
        {
            $this->fail(
                "Unsafe: tests are using connection «{$name}» ({$driver}) instead of sqlite. ".
                'They would wipe or reset your real database. Use `vendor/bin/phpunit` with phpunit.xml, '.
                'or set DB_CONNECTION=sqlite and DB_DATABASE=:memory: (see phpunit.xml <env>). '.
                'To bypass in rare cases: ALLOW_UNSAFE_TEST_DB=1 (never on production).',
            );
        }

        $allowedPath = $database === ':memory:'
            || (str_contains($database, 'testing') && str_ends_with($database, '.sqlite'))
            || str_ends_with($database, 'test.sqlite')
            || str_ends_with($database, 'testing.sqlite');
        if (! $allowedPath)
        {
            $this->fail(
                "Unsafe: sqlite must be :memory: or a file path that clearly means testing (e.g. *testing*.sqlite), not your dev data file. Current database value: \"{$database}\". See phpunit.xml.",
            );
        }
    }
}
