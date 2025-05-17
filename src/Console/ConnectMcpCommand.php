<?php

namespace LucianoTonet\TelescopeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConnectMcpCommand extends Command
{
    protected $signature = 'telescope:mcp-connect {url=http://localhost/mcp}';
    protected $description = 'Connect Cursor to Telescope MCP server';

    public function handle()
    {
        $url = $this->argument('url');
        
        $this->info("Connecting to MCP at $url");
        $this->info("Run this command in your terminal:");
        $this->newLine();
        $this->line("npx -y mcp-remote $url --allow-http");
        $this->newLine();
        $this->info("Then, configure Cursor:");
        $this->info("1. Go to Settings > Model Context Providers");
        $this->info("2. Add a new provider with URL: $url");
        
        Log::info("MCP Connect command executed for URL: $url");
        
        return Command::SUCCESS;
    }
}
