<?php

namespace LucianoTonet\TelescopeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConnectMcpCommand extends Command
{
    protected $signature = 'telescope:mcp-connect {url=http://127.0.0.1:8000/telescope-mcp}';
    protected $description = 'Connect Telescope MCP server to your AI assistant';

    public function handle()
    {
        $url = $this->argument('url');

        $this->info('Telescope MCP Server is ready.');
        $this->newLine();

        $this->comment('1. Start the remote proxy:');
        $this->line("   npx -y mcp-remote $url --allow-http");
        $this->newLine();

        $this->comment('2. Configure your AI Assistant (Cursor, Windsurf, Claude Desktop, etc.):');
        $this->line("   Add a new MCP provider using the URL: $url");
        $this->newLine();

        $this->info('Happy debugging!');

        Log::info("MCP Connect command executed for URL: $url");

        return Command::SUCCESS;
    }
}
