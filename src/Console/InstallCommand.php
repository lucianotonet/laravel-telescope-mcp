<?php

namespace LucianoTonet\TelescopeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'telescope-mcp:install';

    protected $description = 'Install the Telescope MCP package and register with Laravel Boost';

    public function handle(): int
    {
        $this->info('Installing Telescope MCP...');

        // Publish configuration
        $this->call('vendor:publish', [
            '--tag' => 'telescope-mcp-config',
        ]);

        // Register with Boost if configured
        if (file_exists(base_path('boost.json'))) {
            $this->registerWithBoost();
        } else {
            $this->warn('Laravel Boost configuration (boost.json) not found. Skipping Boost registration.');
        }

        $this->info('Telescope MCP installed successfully.');

        return self::SUCCESS;
    }

    protected function registerWithBoost(): void
    {
        $boostConfigPath = base_path('boost.json');
        
        try {
            $config = json_decode(File::get($boostConfigPath), true);
            
            if (!is_array($config)) {
                $this->error('Invalid boost.json file.');
                return;
            }

            $packageName = 'lucianotonet/laravel-telescope-mcp';

            if (! isset($config['packages'])) {
                $config['packages'] = [];
            }

            if (! in_array($packageName, $config['packages'])) {
                $config['packages'][] = $packageName;
                
                // Sort packages alphabetically for cleaner config
                sort($config['packages']);

                File::put($boostConfigPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->info('Registered package with Laravel Boost configuration.');
                
                // Run boost:update to apply changes
                $this->info('Updating Boost guidelines and skills...');
                $this->call('boost:update');
            } else {
                $this->comment('Package already registered in boost.json.');
            }
        } catch (\Exception $e) {
            $this->error('Failed to update boost.json: ' . $e->getMessage());
        }
    }
}
