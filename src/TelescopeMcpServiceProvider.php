<?php

namespace LucianoTonet\TelescopeMcp;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LucianoTonet\TelescopeMcp\Console\ConnectMcpCommand;
use LucianoTonet\TelescopeMcp\Support\Logger;

class TelescopeMcpServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/telescope-mcp.php' => config_path('telescope-mcp.php'),
            ], 'telescope-mcp-config');
            
            $this->commands([
                ConnectMcpCommand::class,
            ]);

            $this->checkLaravelBoost();
        }
        
        // Configurar logger
        $this->configureLogging();
        
        // Registrar rota de teste para gerar entradas no Telescope
        $this->registerTestRoute();
    }

    protected function checkLaravelBoost()
    {
        if (in_array('package:discover', $_SERVER['argv'] ?? [])) {
            if (class_exists(\Laravel\Boost\BoostServiceProvider::class)) {
                $msg = "\n\033[33mLaravel Boost was detected. For better integration with Boost, consider using the dedicated package: lucianotonet/laravel-boost-telescope (instead of this one).\033[0m\n\033[33m  composer require lucianotonet/laravel-boost-telescope --dev\033[0m\n\n";
                fwrite(STDERR, $msg);
            }
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/telescope-mcp.php', 'telescope-mcp'
        );
    }

    protected function registerRoutes()
    {
        Route::group([
            'prefix' => config('telescope-mcp.path', 'telescope-mcp'),
            'middleware' => config('telescope-mcp.middleware', ['web'])
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
    
    protected function configureLogging()
    {
        // Inicializar logger
        Logger::getInstance();
    }
    
    protected function registerTestRoute()
    {
        Route::get('/telescope-mcp-test', function () {
            Logger::info('Test route accessed');
            return response()->json(['message' => 'Teste do Telescope MCP']);
        });
    }
} 