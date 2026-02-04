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
    protected $signature = 'telescope-mcp:server';

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
            $transport = new \Laravel\Mcp\Server\Transport\StdioTransport(uniqid());
            $server = new TelescopeServer($transport);

            $server->start();

            $this->info('Telescope MCP Server started in stdio mode', 'stderr');

            $transport->run();

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
    /**
     * Write to stderr for logging
     */
    public function info($message, $string = 'info'): void
    {
        if ($string === 'stderr') {
            fwrite(STDERR, "[INFO] {$message}\n");
        } else {
            parent::info($message);
        }
    }

    /**
     * Write error to stderr
     */
    public function error($message, $string = 'error'): void
    {
        if ($string === 'stderr') {
            fwrite(STDERR, "[ERROR] {$message}\n");
        } else {
            parent::error($message);
        }
    }
}
