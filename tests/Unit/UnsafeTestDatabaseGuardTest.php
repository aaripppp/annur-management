<?php

use Tests\TestCase;

uses(TestCase::class);

it('permits the configured in-memory sqlite test database', function () {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(config('database.connections.sqlite.url'))->toBeFalsy();

    expect(fn () => $this->guardAgainstUnsafeTestDatabase())
        ->not->toThrow(RuntimeException::class);
});

it('aborts when the default connection is not sqlite', function () {
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.database', 'annur_management_fix');

    $this->guardAgainstUnsafeTestDatabase();
})->throws(
    RuntimeException::class,
    'Unsafe test database configuration. Expected sqlite :memory:, got mysql / annur_management_fix. Tests aborted to protect development data.'
);

it('aborts when sqlite is pointed at a file instead of an in-memory database', function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', database_path('database.sqlite'));

    $this->guardAgainstUnsafeTestDatabase();
})->throws(
    RuntimeException::class,
    'Unsafe test database configuration. Expected sqlite :memory:, got sqlite / ',
);

it('aborts when a database url could override the resolved database name', function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('database.connections.sqlite.url', 'sqlite:///Users/arifhamdani/annur_management.sqlite');

    $this->guardAgainstUnsafeTestDatabase();
})->throws(RuntimeException::class, 'Unsafe test database configuration.');

it('names the cached config file when a config cache is what overrode the test env', function () {
    config()->set('app.config_cached', true);
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.database', 'annur_management_fix');

    $this->guardAgainstUnsafeTestDatabase();
})->throws(RuntimeException::class, 'bootstrap/cache/config.php overrides the phpunit.xml env values; run "php artisan config:clear".');
