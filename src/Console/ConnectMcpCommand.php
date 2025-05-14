<?php

namespace LucianoTonet\TelescopeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConnectMcpCommand extends Command
{
    protected $signature = 'telescope:mcp-connect {url=http://localhost/mcp}';
    protected $description = 'Conectar o Cursor ao servidor MCP do Telescope';

    public function handle()
    {
        $url = $this->argument('url');
        
        $this->info("Conectando ao MCP em $url");
        $this->info("Execute este comando no seu terminal:");
        $this->newLine();
        $this->line("npx -y mcp-remote $url --allow-http");
        $this->newLine();
        $this->info("Depois, configure o Cursor:");
        $this->info("1. Vá para Settings > Model Context Providers");
        $this->info("2. Adicione um novo provider com URL: $url");
        
        Log::info("MCP Connect command executed for URL: $url");
        
        return Command::SUCCESS;
    }
} 