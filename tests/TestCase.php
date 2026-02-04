<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            TelescopeServiceProvider::class,
            TelescopeMcpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Configure Telescope MCP
        $app['config']->set('telescope-mcp', [
            'enabled' => true,
            'path' => 'telescope-mcp',
            'middleware' => ['web'],
            'logging' => [
                'enabled' => true,
                'level' => 'debug',
                'path' => storage_path('logs/telescope-mcp-test.log'),
                'channel' => 'stack',
            ],
        ]);

        // Configure Telescope
        $app['config']->set('telescope.enabled', true);
        $app['config']->set('telescope.storage.driver', 'database');
        $app['config']->set('telescope.storage.database.connection', 'testbench');
        // Prevent Telescope from attempting to migrate in every test after the first run with RefreshDatabase
        $app['config']->set('telescope.migrations', false);

        // Ensure log directory exists
        $logDir = dirname(storage_path('logs/telescope-mcp-test.log'));
        if (!is_dir($logDir)) {
            mkdir($logDir, 0o755, true);
        }

        // Set application key for encryption
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }

    protected function defineRoutes($router)
    {
        // Define any additional routes needed for testing
        $router->get('/test-route', function () {
            abort(404);
        });
    }
}
