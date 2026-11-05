<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class MysqlConcurrencyTestCase extends BaseTestCase
{
    public ?string $mysqlStorageRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('PPDB_MYSQL_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set PPDB_MYSQL_CONCURRENCY_TEST=1 to run the isolated MySQL concurrency suite.');
        }

        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('The pcntl extension is required for real process concurrency.');
        }

        $this->assertSafeMysqlDatabase();
    }

    private function assertSafeMysqlDatabase(): void
    {
        $connection = (string) config('database.default');
        $selectedDatabase = (string) config("database.connections.{$connection}.database");
        $expectedDatabase = (string) env('PPDB_MYSQL_CONCURRENCY_DATABASE');
        $applicationDatabase = (string) env('PPDB_MYSQL_APPLICATION_DATABASE');

        if (! app()->environment('testing')) {
            throw new RuntimeException('MySQL concurrency tests require APP_ENV=testing.');
        }

        if ($connection !== 'mysql') {
            throw new RuntimeException('MySQL concurrency tests require DB_CONNECTION=mysql.');
        }

        if ($expectedDatabase === '' || $selectedDatabase !== $expectedDatabase) {
            throw new RuntimeException('The selected database must exactly match PPDB_MYSQL_CONCURRENCY_DATABASE.');
        }

        if (! preg_match('/\A[a-zA-Z0-9_]+_test\z/', $selectedDatabase)) {
            throw new RuntimeException('The concurrency database name must end with _test.');
        }

        if ($applicationDatabase === '' || hash_equals($applicationDatabase, $selectedDatabase)) {
            throw new RuntimeException('PPDB_MYSQL_APPLICATION_DATABASE must be set and differ from the concurrency database.');
        }
    }
}
