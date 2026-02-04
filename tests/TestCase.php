<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Telescope\TelescopeServiceProvider;
use LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Force using default application resolution so Console Kernel is always registered.
     * When the bootstrap file is used, Testbench skips kernel registration and artisan() fails.
     */
    protected function getApplicationBootstrapFile(string $filename): string|false
    {
        return false;
    }

    /**
     * @return Application
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $this->ensureConsoleKernelBound($app);
        return $app;
    }

    protected function refreshApplication()
    {
        parent::refreshApplication();
        if ($this->app) {
            $this->ensureConsoleKernelBound($this->app);
        }
    }

    private function ensureConsoleKernelBound($app): void
    {
        if (!$app->bound(ConsoleKernelContract::class)) {
            $app->singleton(ConsoleKernelContract::class, \Orchestra\Testbench\Console\Kernel::class);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            TelescopeServiceProvider::class,
            TelescopeMcpServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('telescope.storage.database.connection', 'testbench');
        $app['config']->set('telescope.enabled', true);

        $app['config']->set('telescope-mcp.enabled', true);
        $app['config']->set('telescope-mcp.logging.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $telescopePath = base_path('vendor/laravel/telescope/database/migrations');
        if (is_dir($telescopePath)) {
            $this->loadMigrationsFrom($telescopePath);
        }
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--path' => 'vendor/laravel/telescope/database/migrations',
        ]);
    }
}
