<?php

namespace LucianoTonet\TelescopeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallMcpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telescope-mcp:install
                            {--force : Overwrite existing MCP configuration}
                            {--global : Install globally instead of project-specific}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Telescope MCP server configuration for AI agents';

    /**
     * Available MCP clients and their configuration paths
     *
     * @var array
     */
    protected array $mcpClients = [
        'cursor' => [
            'name' => 'Cursor',
            'config_path' => '~/.cursor/mcp.json',
            'auto_detected' => true,
            'type' => 'json',
        ],
        'claude-code' => [
            'name' => 'Claude Code',
            'config_path' => '~/.claude/mcp.json',
            'auto_detected' => true,
            'type' => 'json',
        ],
        'windsurf' => [
            'name' => 'Windsurf',
            'config_path' => '~/.windsurf/mcp.json',
            'auto_detected' => true,
            'type' => 'json',
        ],
        'gemini' => [
            'name' => 'Gemini App',
            'config_path' => '~/.gemini/settings.json',
            'auto_detected' => true,
            'type' => 'json',
        ],
        'codex' => [
            'name' => 'Codex',
            'config_path' => '~/.codex/config.toml',
            'auto_detected' => true,
            'type' => 'toml',
        ],
        'opencode' => [
            'name' => 'OpenCode',
            'config_path' => '~/.config/opencode/opencode.json',
            'auto_detected' => true,
            'type' => 'json',
        ],
        'cline' => [
            'name' => 'Cline (VS Code)',
            'config_path' => '~/.config/Code/User/globalStorage/saoudrizwan.claude-dev/settings/cline_mcp_settings.json',
            'auto_detected' => false,
            'type' => 'json',
        ],
        'project' => [
            'name' => 'Project-specific (.mcp.json)',
            'config_path' => '.mcp.json',
            'auto_detected' => false,
            'type' => 'json',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Laravel Telescope MCP Installation');
        $this->newLine();

        // Detect available MCP clients
        $detectedClients = $this->detectMcpClients();

        if (empty($detectedClients) && !$this->option('global')) {
            $this->components->warn('No MCP clients detected automatically.');
            $this->newLine();

            if ($this->confirm('Would you like to create a project-specific .mcp.json file?', true)) {
                $detectedClients = ['project'];
            } else {
                $this->components->info('You can manually configure MCP later using the details below:');
                $this->displayManualInstructions();
                return self::SUCCESS;
            }
        }

        // Ask user which clients to configure
        $selectedClients = $this->selectClients($detectedClients);

        if (empty($selectedClients)) {
            $this->components->warn('No clients selected. Installation cancelled.');
            return self::FAILURE;
        }

        // Install configuration for each selected client
        $successCount = 0;
        foreach ($selectedClients as $client) {
            if ($this->installForClient($client)) {
                $successCount++;
            }
        }

        $this->newLine();

        if ($successCount > 0) {
            $this->components->info("✓ Successfully configured {$successCount} MCP client(s)");
            $this->newLine();
            $this->displayNextSteps($selectedClients);
        } else {
            $this->components->error('No configurations were updated.');
        }

        return self::SUCCESS;
    }

    /**
     * Detect available MCP clients on the system
     */
    protected function detectMcpClients(): array
    {
        $detected = [];

        foreach ($this->mcpClients as $key => $client) {
            if (!$client['auto_detected']) {
                continue;
            }

            $configPath = $this->expandPath($client['config_path']);

            // Check if config file exists or if parent directory exists
            if (File::exists($configPath) || File::exists(dirname($configPath))) {
                $detected[] = $key;
            }
        }

        return $detected;
    }

    /**
     * Ask user to select which clients to configure
     */
    protected function selectClients(array $detectedClients): array
    {
        if (count($detectedClients) === 1) {
            $client = $detectedClients[0];
            $clientName = $this->mcpClients[$client]['name'];

            if ($this->confirm("Configure MCP for {$clientName}?", true)) {
                return [$client];
            }
            return [];
        }

        $choices = [];
        foreach ($detectedClients as $key) {
            $choices[$key] = $this->mcpClients[$key]['name'];
        }

        $selected = $this->choice(
            'Which MCP clients would you like to configure? (comma-separated for multiple)',
            array_merge(['all' => 'All detected clients'], $choices),
            'all'
        );

        if ($selected === 'all') {
            return $detectedClients;
        }

        return array_filter($detectedClients, fn($key) => $key === $selected);
    }

    /**
     * Install MCP configuration for a specific client
     */
    protected function installForClient(string $clientKey): bool
    {
        $client = $this->mcpClients[$clientKey];
        $configPath = $this->expandPath($client['config_path']);
        $type = $client['type'] ?? 'json';

        // Ensure directory exists
        $directory = dirname($configPath);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        try {
            if ($type === 'toml') {
                return $this->updateTomlConfig($configPath, $client['name']);
            }

            // Load existing config or create new
            $config = $this->loadMcpConfig($configPath);

            // Add/Update Telescope MCP server
            $config['mcpServers']['laravel-telescope'] = $this->getMcpServerConfig();

            // Write config
            File::put(
                $configPath,
                json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
            );

            $this->components->task(
                "Configured {$client['name']}",
                fn() => true
            );

            return true;
        } catch (\Exception $e) {
            $this->components->error("Failed to configure {$client['name']}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Update TOML configuration file
     */
    protected function updateTomlConfig(string $path, string $clientName): bool
    {
        $serverConfig = $this->getMcpServerConfig();
        $tomlConfig = $this->getMcpServerConfigToml($serverConfig);

        try {
            if (!File::exists($path)) {
                File::put($path, $tomlConfig);
            } else {
                $content = File::get($path);
                
                // Check if already configured to avoid duplication
                if (str_contains($content, '[mcpServers.laravel-telescope]')) {
                   $this->components->warn("Laravel Telescope already configured in {$path}");
                   return true;
                }

                File::append($path, "\n" . $tomlConfig);
            }

            $this->components->task(
                "Configured {$clientName}",
                fn() => true
            );

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Load existing MCP configuration or return empty structure
     */
    protected function loadMcpConfig(string $path): array
    {
        if (!File::exists($path)) {
            return ['mcpServers' => []];
        }

        try {
            $content = File::get($path);
            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->components->warn("Existing config is invalid JSON. Creating backup...");
                File::copy($path, $path . '.backup');
                return ['mcpServers' => []];
            }

            return $config;
        } catch (\Exception $e) {
            $this->components->warn("Could not read existing config: {$e->getMessage()}");
            return ['mcpServers' => []];
        }
    }

    /**
     * Get MCP server configuration array
     */
    protected function getMcpServerConfig(): array
    {
        $basePath = base_path();

        return [
            'command' => 'php',
            'args' => [
                'artisan',
                'telescope-mcp:server'
            ],
            'cwd' => $basePath,
            'env' => [
                'APP_ENV' => config('app.env', 'local'),
            ],
        ];
    }

    /**
     * Get MCP server configuration in TOML format
     */
    protected function getMcpServerConfigToml(array $config): string
    {
        $toml = "[mcpServers.laravel-telescope]\n";
        $toml .= "command = \"{$config['command']}\"\n";
        
        $args = array_map(fn($arg) => "\"{$arg}\"", $config['args']);
        $toml .= "args = [" . implode(', ', $args) . "]\n";
        
        // Escape backslashes in Windows paths for TOML
        $cwd = str_replace('\\', '\\\\', $config['cwd']);
        $toml .= "cwd = \"{$cwd}\"\n";
        
        if (!empty($config['env'])) {
            $toml .= "\n[mcpServers.laravel-telescope.env]\n";
            foreach ($config['env'] as $key => $value) {
                $toml .= "{$key} = \"{$value}\"\n";
            }
        }
        
        return $toml;
    }

    /**
     * Display manual installation instructions
     */
    protected function displayManualInstructions(): void
    {
        $this->newLine();
        $this->line('<fg=yellow>Manual MCP Configuration</>');
        $this->line(str_repeat('─', 60));
        $this->newLine();

        $config = $this->getMcpServerConfig();

        $this->line('Add this to your MCP configuration file:');
        $this->newLine();

        $this->line('<fg=cyan>' . json_encode([
            'mcpServers' => [
                'laravel-telescope' => $config
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</>');

        $this->newLine();
        $this->line('Common MCP config locations:');
        foreach ($this->mcpClients as $key => $client) {
            $this->line("  • {$client['name']}: <fg=gray>{$client['config_path']}</>");
        }
    }

    /**
     * Display next steps after installation
     */
    protected function displayNextSteps(array $installedClients): void
    {
        $this->line('<fg=yellow>Next Steps:</>');
        $this->line(str_repeat('─', 60));
        $this->newLine();

        foreach ($installedClients as $clientKey) {
            $client = $this->mcpClients[$clientKey];

            $this->line("<fg=cyan>{$client['name']}</>");

            switch ($clientKey) {
                case 'cursor':
                    $this->line('  1. Open command palette (Cmd/Ctrl + Shift + P)');
                    $this->line('  2. Type "MCP Settings" and press Enter');
                    $this->line('  3. Toggle ON "laravel-telescope"');
                    break;

                case 'claude-code':
                    $this->line('  • Server should auto-enable on next restart');
                    $this->line('  • Or run: claude mcp add -s local -t stdio laravel-telescope php artisan telescope:mcp');
                    break;

                case 'windsurf':
                    $this->line('  1. Open Windsurf settings');
                    $this->line('  2. Navigate to MCP Servers');
                    $this->line('  3. Enable "laravel-telescope"');
                    break;
                
                case 'gemini':
                case 'opencode':
                case 'codex':
                    $this->line('  • Server should auto-enable on next restart');
                    break;

                case 'project':
                    $this->line('  • MCP clients in this project will auto-detect .mcp.json');
                    break;

                default:
                    $this->line('  • Restart your IDE/editor to load the new configuration');
            }

            $this->newLine();
        }

        $this->line('<fg=green>Available Tools (19 total):</>');
        $this->line('  requests, logs, exceptions, queries, jobs, cache, commands,');
        $this->line('  dumps, events, gates, http-client, mail, models, notifications,');
        $this->line('  redis, schedule, views, batches, prune');
        $this->newLine();

        $this->line('Test the server with:');
        $this->line('  <fg=cyan>php artisan telescope:mcp</> (runs in stdio mode)');
    }

    /**
     * Expand ~ to home directory
     */
    protected function expandPath(string $path): string
    {
        if (str_starts_with($path, '~')) {
            $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
            return $home . substr($path, 1);
        }

        if (str_starts_with($path, '.')) {
            return base_path($path);
        }

        return $path;
    }
}
