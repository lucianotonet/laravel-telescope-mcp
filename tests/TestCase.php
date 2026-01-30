<?php

namespace Tests;

use Laravel\Telescope\TelescopeServiceProvider;
use LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TelescopeServiceProvider::class,
            TelescopeMcpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure Telescope MCP
        $app['config']->set('telescope-mcp', [
            'enabled' => true,
            'logging' => [
                'enabled' => true,
                'channel' => 'stack',
            ],
        ]);

        // Configure Telescope
        $app['config']->set('telescope.enabled', true);
        $app['config']->set('telescope.storage.driver', 'database');
        $app['config']->set('telescope.storage.database.connection', 'testbench');
        $app['config']->set('telescope.migrations', false);

        // Set application key for encryption
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/telescope/database/migrations');
    }
}
