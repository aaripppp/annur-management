<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the application, then make sure it points at a throwaway database.
     *
     * The guard runs in this hook because it sits between application boot and
     * the setUpTraits() call inside parent::setUp(). RefreshDatabase migrates
     * and wraps transactions from a trait setUp hook, so aborting here stops
     * the run before any database statement is issued.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->guardAgainstUnsafeTestDatabase();
    }

    /**
     * Abort unless the runtime database is an in-memory sqlite database.
     *
     * The check reads the resolved config rather than the environment, so a
     * cached bootstrap/cache/config.php pointing at a real database is caught
     * even when phpunit.xml declares the safe values.
     */
    protected function guardAgainstUnsafeTestDatabase(): void
    {
        $config = $this->app->make('config');

        $connection = $config->get('database.default');
        $database = $config->get("database.connections.{$connection}.database");
        $url = $config->get("database.connections.{$connection}.url");

        if ($connection === 'sqlite' && $database === ':memory:' && empty($url)) {
            return;
        }

        $cacheHint = $config->get('app.config_cached')
            ? ' Cached config found in bootstrap/cache/config.php overrides the phpunit.xml env values; run "php artisan config:clear".'
            : '';

        throw new RuntimeException(sprintf(
            'Unsafe test database configuration. Expected sqlite :memory:, got %s / %s. Tests aborted to protect development data.%s',
            is_string($connection) ? $connection : gettype($connection),
            is_string($database) ? $database : gettype($database),
            $cacheHint,
        ));
    }
}
