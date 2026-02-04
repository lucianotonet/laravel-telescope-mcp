<?php

use Laravel\Mcp\Server\Contracts\Transport;
use LucianoTonet\TelescopeMcp\Mcp\Servers\TelescopeServer;

test('telescope server has correct name', function () {
    $reflection = new \ReflectionClass(TelescopeServer::class);
    $property = $reflection->getProperty('name');
    $property->setAccessible(true);

    $server = new TelescopeServer(Mockery::mock(Transport::class));
    $name = $property->getValue($server);

    expect($name)->toBe('Laravel Telescope MCP');
});

test('telescope server has correct version', function () {
    $reflection = new \ReflectionClass(TelescopeServer::class);
    $property = $reflection->getProperty('version');
    $property->setAccessible(true);

    $server = new TelescopeServer(Mockery::mock(Transport::class));
    $version = $property->getValue($server);

    expect($version)->toBe('2.0.0');
});

test('telescope server default pagination length is 50', function () {
    $server = new TelescopeServer(Mockery::mock(Transport::class));

    expect($server->defaultPaginationLength)->toBe(50);
});

test('telescope server registers all expected tools', function () {
    $reflection = new \ReflectionClass(TelescopeServer::class);
    $property = $reflection->getProperty('tools');
    $property->setAccessible(true);

    $server = new TelescopeServer(Mockery::mock(Transport::class));
    $tools = $property->getValue($server);

    $expectedTools = [
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

    expect($tools)->toHaveCount(count($expectedTools));
    foreach ($expectedTools as $expected) {
        expect($tools)->toContain($expected);
    }
});

test('telescope server has empty resources', function () {
    $reflection = new \ReflectionClass(TelescopeServer::class);
    $property = $reflection->getProperty('resources');
    $property->setAccessible(true);

    $server = new TelescopeServer(Mockery::mock(Transport::class));
    $resources = $property->getValue($server);

    expect($resources)->toBeArray();
    expect($resources)->toBeEmpty();
});

test('telescope server instructions mention telescope and tools', function () {
    $reflection = new \ReflectionClass(TelescopeServer::class);
    $property = $reflection->getProperty('instructions');
    $property->setAccessible(true);

    $server = new TelescopeServer(Mockery::mock(Transport::class));
    $instructions = $property->getValue($server);

    expect($instructions)->toContain('Telescope');
    expect($instructions)->toContain('tools');
});
