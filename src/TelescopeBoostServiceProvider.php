<?php

namespace LucianoTonet\TelescopeMcp;

use Illuminate\Support\ServiceProvider;
use LucianoTonet\TelescopeMcp\Support\Logger;

/**
 * Service Provider for Laravel Boost integration.
 *
 * This provider handles the registration of Telescope MCP as a Boost Package Skill,
 * enabling automatic discovery of guidelines, skills, and tools.
 */
class TelescopeBoostServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Boost resources for auto-discovery
        $this->registerBoostResources();

        // Register tools in Boost if available
        if ($this->boostIsInstalled()) {
            $this->registerBoostTools();
            Logger::debug('Laravel Boost detected. Telescope tools registered successfully.');
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // This provider is focused on Boost integration
        // Core MCP functionality is in TelescopeMcpServiceProvider
    }

    /**
     * Register Boost resources (guidelines and skills) for auto-discovery.
     *
     * Laravel Boost automatically discovers resources from packages that
     * have a resources/boost directory with guidelines and skills.
     */
    protected function registerBoostResources(): void
    {
        $boostResourcesPath = __DIR__.'/../resources/boost';

        // Register views from the boost resources path so Laravel can find them
        // This enables Boost to discover our guidelines and skills
        if (is_dir($boostResourcesPath)) {
            $this->loadViewsFrom($boostResourcesPath.'/guidelines', 'telescope-mcp-boost');
        }

        // Publish Boost resources when running in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $boostResourcesPath.'/guidelines' => resource_path('boost/guidelines/telescope-mcp'),
                $boostResourcesPath.'/skills' => resource_path('boost/skills'),
            ], 'boost');
        }
    }

    /**
     * Register Telescope tools in Laravel Boost via configuration.
     */
    protected function registerBoostTools(): void
    {
        // Get current Boost tools configuration
        $currentTools = config('boost.mcp.tools.include', []);

        // Add all Telescope tools to the include list
        $telescopeTools = [
            // Core Debugging Tools
            BoostExtension\Tools\TelescopeExceptionsTool::class,
            BoostExtension\Tools\TelescopeQueriesTool::class,
            BoostExtension\Tools\TelescopeRequestsTool::class,
            BoostExtension\Tools\TelescopeLogsTool::class,
            
            // Queue & Jobs Tools
            BoostExtension\Tools\TelescopeJobsTool::class,
            BoostExtension\Tools\TelescopeBatchesTool::class,
            
            // Cache & Data Tools
            BoostExtension\Tools\TelescopeCacheTool::class,
            BoostExtension\Tools\TelescopeRedisTool::class,
            BoostExtension\Tools\TelescopeModelsTool::class,
            
            // Communication Tools
            BoostExtension\Tools\TelescopeMailTool::class,
            BoostExtension\Tools\TelescopeNotificationsTool::class,
            
            // System Tools
            BoostExtension\Tools\TelescopeCommandsTool::class,
            BoostExtension\Tools\TelescopeScheduleTool::class,
            BoostExtension\Tools\TelescopeEventsTool::class,
            BoostExtension\Tools\TelescopeGatesTool::class,
            BoostExtension\Tools\TelescopeViewsTool::class,
            BoostExtension\Tools\TelescopeDumpsTool::class,
            BoostExtension\Tools\TelescopeHttpClientTool::class,
            
            // Maintenance Tools
            BoostExtension\Tools\TelescopePruneTool::class,
        ];

        // Merge with existing tools
        config(['boost.mcp.tools.include' => array_merge($currentTools, $telescopeTools)]);
    }

    /**
     * Check if Laravel Boost is installed.
     */
    protected function boostIsInstalled(): bool
    {
        return class_exists(\Laravel\Boost\Boost::class);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
