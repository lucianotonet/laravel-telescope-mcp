<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Servers;

use Laravel\Mcp\Server;

/**
 * Laravel Telescope MCP Server
 *
 * Provides access to Laravel Telescope monitoring data through the Model Context Protocol.
 * Exposes tools for querying requests, logs, queries, exceptions, jobs, cache operations,
 * and other Telescope entries.
 */
class TelescopeServer extends Server
{
    /**
     * The server name
     *
     * @var string
     */
    protected string $name = 'Laravel Telescope MCP';

    /**
     * The server version
     *
     * @var string
     */
    protected string $version = '2.0.0';

    /**
     * The default pagination length.
     *
     * @var int
     */
    public int $defaultPaginationLength = 50;

    /**
     * Instructions for using this server
     *
     * @var string
     */
    protected string $instructions = 'Provides access to Laravel Telescope monitoring data including HTTP requests, database queries, logs, exceptions, jobs, cache operations, and more. Use the available tools to query and analyze your application\'s runtime behavior.';

    /**
     * Tools provided by this server
     *
     * Tools will be automatically discovered from the src/Mcp/Tools directory
     *
     * @var array
     */
    protected array $tools = [
        \LucianoTonet\TelescopeMcp\Mcp\Tools\RequestsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\LogsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\ExceptionsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\QueriesTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\BatchesTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\CacheTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\CommandsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\DumpsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\EventsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\GatesTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\HttpClientTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\JobsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\MailTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\ModelsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\NotificationsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\RedisTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\ScheduleTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\ViewsTool::class,
        \LucianoTonet\TelescopeMcp\Mcp\Tools\PruneTool::class,
    ];

    /**
     * Resources provided by this server
     *
     * @var array
     */
    protected array $resources = [];

    /**
     * Prompts provided by this server
     *
     * @var array
     */
    protected array $prompts = [];
}
