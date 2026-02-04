<?php

namespace LucianoTonet\TelescopeMcp\Console;

use Illuminate\Console\Command;
use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Mcp\Servers\TelescopeServer;

class McpServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telescope:mcp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Laravel Telescope MCP server in stdio mode';

    /**
     * Execute the console command.
     */
    public function handle(EntriesRepository $repository): int
    {
        // Set up error logging to stderr only (stdio mode requirement)
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', 'php://stderr');

        try {
            // Create and run the Telescope MCP server
            $server = new TelescopeServer();

            // Laravel MCP framework will handle stdio communication automatically
            // The server will read from stdin and write to stdout

            // Note: In Laravel MCP v0.5.3, servers run via artisan commands
            // automatically enter stdio mode when called from MCP clients

            // The framework handles:
            // - Reading JSON-RPC requests from stdin
            // - Routing to appropriate tools
            // - Writing JSON-RPC responses to stdout

            $this->info('Telescope MCP Server started in stdio mode', 'stderr');
            $this->info('Waiting for JSON-RPC requests...', 'stderr');

            // Keep the process running
            // Laravel MCP will handle the actual stdio loop
            while (true) {
                sleep(1);
            }

        } catch (\Exception $e) {
            $this->error('MCP Server Error: ' . $e->getMessage(), 'stderr');
            $this->error($e->getTraceAsString(), 'stderr');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Write to stderr for logging
     */
    protected function info($message, $stream = 'stdout'): void
    {
        if ($stream === 'stderr') {
            fwrite(STDERR, "[INFO] {$message}\n");
        } else {
            parent::info($message);
        }
    }

    /**
     * Write error to stderr
     */
    protected function error($message, $stream = 'stdout'): void
    {
        if ($stream === 'stderr') {
            fwrite(STDERR, "[ERROR] {$message}\n");
        } else {
            parent::error($message);
        }
    }
}
