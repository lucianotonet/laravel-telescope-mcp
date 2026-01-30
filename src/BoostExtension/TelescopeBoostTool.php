<?php

namespace LucianoTonet\TelescopeMcp\BoostExtension;

use Laravel\Mcp\Server\Tool;
use LucianoTonet\TelescopeMcp\MCP\TelescopeMcpServer;

/**
 * Base class for Telescope MCP Tools that integrate with Laravel Boost.
 */
abstract class TelescopeBoostTool extends Tool
{
    protected TelescopeMcpServer $server;

    public function __construct()
    {
        $this->server = app(TelescopeMcpServer::class);
    }

    /**
     * Execute the tool with given arguments.
     *
     * @param  array<string, mixed>|\Laravel\Mcp\Request  $arguments
     */
    public function handle(array|\Laravel\Mcp\Request $arguments): \Laravel\Mcp\Response
    {
        if ($arguments instanceof \Laravel\Mcp\Request) {
            $arguments = $arguments->all();
        }

        // Extract the tool name from the class name
        $toolName = $this->getToolNameFromClass();

        // Execute through TelescopeMcpServer
        $result = $this->server->executeTool($toolName, $arguments);

        // Return structured JSON response as expected by Laravel Boost
        return \Laravel\Mcp\Response::json($result);
    }

    /**
     * Extract tool name from class name.
     * Example: TelescopeExceptionsTool -> exceptions
     * Example: TelescopeHttpClientTool -> http-client
     */
    protected function getToolNameFromClass(): string
    {
        $className = class_basename($this);

        // Remove 'Telescope' prefix and 'Tool' suffix
        $name = str_replace(['Telescope', 'Tool'], '', $className);

        // Convert to kebab-case as used internally by Telescope MCP tools
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }
}
