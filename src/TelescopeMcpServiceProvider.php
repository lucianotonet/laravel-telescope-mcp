<?php

namespace LucianoTonet\TelescopeMcp;

use Illuminate\Support\ServiceProvider;
use LucianoTonet\TelescopeMcp\Support\Logger;
use RuntimeException;

/**
 * Main Service Provider for Laravel Telescope MCP.
 *
 * This provider registers the Telescope MCP tools for Laravel Boost integration.
 * It requires Laravel Telescope and Laravel Boost to function.
 */
class TelescopeMcpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureTelescopeIsInstalled();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->registerCommands();
        }

        $this->configureLogging();
    }

    /**
     * Ensure Laravel Telescope is installed.
     *
     * @throws RuntimeException
     */
    protected function ensureTelescopeIsInstalled(): void
    {
        if (! class_exists(\Laravel\Telescope\Telescope::class)) {
            throw new RuntimeException(
                'Laravel Telescope is required for Telescope MCP to work. '.
                'Please install it with: composer require laravel/telescope --dev'
            );
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/telescope-mcp.php',
            'telescope-mcp'
        );

        // Register the Boost provider for skill discovery
        $this->app->register(TelescopeBoostServiceProvider::class);
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/telescope-mcp.php' => config_path('telescope-mcp.php'),
        ], 'telescope-mcp-config');
    }

    /**
     * Register the package's Artisan commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            Console\InstallCommand::class,
            Console\GenerateBoostToolsCommand::class,
        ]);
    }

    /**
     * Configure the logging system.
     */
    protected function configureLogging(): void
    {
        Logger::getInstance();
    }
}
