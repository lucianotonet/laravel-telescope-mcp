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
        }
        
        // Configurar logger
        $this->configureLogging();
        
        // Registrar rota de teste para gerar entradas no Telescope
        $this->registerTestRoute();
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
            'prefix' => config('telescope-mcp.path', 'mcp'),
            'namespace' => 'LucianoTonet\TelescopeMcp\Http\Controllers',
            'middleware' => config('telescope-mcp.middleware', ['web'])
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
    
    protected function configureLogging()
    {
        // Criar diretório de logs se não existir
        $logPath = dirname(config('telescope-mcp.logging.path'));
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }
        
        // Inicializar logger
        Logger::getInstance();
        
        // Log inicial
        Logger::info('Telescope MCP initialized', [
            'version' => '1.0.0',
            'config' => config('telescope-mcp')
        ]);
    }
    
    protected function registerTestRoute()
    {
        Route::get('/telescope-mcp-test', function () {
            Logger::info('Test route accessed');
            return response()->json(['message' => 'Teste do Telescope MCP']);
        });
    }
} 