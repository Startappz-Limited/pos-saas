<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase drops every table in the test database on each run, and
     * every database except the test database is treated as live. Refuse to
     * start unless the suite points at SQLite in memory or a database whose
     * name ends in "_testing".
     *
     * Runs before the traits are set up, so before any table is touched.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $this->guardAgainstLiveDatabase();

        return parent::setUpTraits();
    }

    protected function guardAgainstLiveDatabase(): void
    {
        $connection = config('database.default');

        static::assertSafeTestDatabase(
            (string) config("database.connections.{$connection}.driver"),
            (string) config("database.connections.{$connection}.database"),
        );
    }

    /**
     * @throws RuntimeException when the database may hold live data
     */
    public static function assertSafeTestDatabase(string $driver, string $database): void
    {
        if ($driver === 'sqlite' && $database === ':memory:') {
            return;
        }

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Refusing to run tests against '{$database}' ({$driver}): only SQLite in memory or a database ".
                "whose name ends in '_testing' may be used. Every other database is treated as live."
            );
        }
    }
}
