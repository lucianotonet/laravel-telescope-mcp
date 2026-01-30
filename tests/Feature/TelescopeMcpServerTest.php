<?php

use LucianoTonet\TelescopeMcp\MCP\TelescopeMcpServer;

beforeEach(function () {
    $this->server = app(TelescopeMcpServer::class);
});

test('server can be instantiated', function () {
    expect($this->server)->toBeInstanceOf(TelescopeMcpServer::class);
});

test('server has manifest', function () {
    $manifest = $this->server->getManifest();

    expect($manifest)
        ->toBeArray()
        ->toHaveKeys(['name', 'version', 'description', 'tools'])
        ->and($manifest['name'])->toBe('Laravel Telescope MCP')
        ->and($manifest['version'])->toBe('1.0.0');
});

test('server has all expected tools registered', function () {
    $manifest = $this->server->getManifest();
    $tools = (array) $manifest['tools'];

    $expectedTools = [
        'requests',
        'logs',
        'exceptions',
        'batches',
        'cache',
        'commands',
        'dumps',
        'events',
        'gates',
        'http-client',
        'jobs',
        'mail',
        'models',
        'notifications',
        'queries',
        'redis',
        'schedule',
        'views',
        'prune',
    ];

    expect(count($tools))->toBe(count($expectedTools));

    foreach ($expectedTools as $tool) {
        expect(array_key_exists($tool, $tools))->toBeTrue("Tool {$tool} should be registered");
    }
});

test('server hasTool returns true for registered tools', function () {
    expect($this->server->hasTool('logs'))->toBeTrue()
        ->and($this->server->hasTool('requests'))->toBeTrue()
        ->and($this->server->hasTool('exceptions'))->toBeTrue();
});

test('server hasTool returns false for unregistered tools', function () {
    expect($this->server->hasTool('nonexistent_tool'))->toBeFalse()
        ->and($this->server->hasTool('invalid'))->toBeFalse();
});

test('server throws exception for nonexistent tool', function () {
    $this->server->executeTool('nonexistent_tool', []);
})->throws(Exception::class, 'Tool not found: nonexistent_tool');

test('each tool has valid schema', function () {
    $manifest = $this->server->getManifest();
    $tools = (array) $manifest['tools'];

    foreach ($tools as $name => $tool) {
        expect($tool)
            ->toHaveKeys(['name', 'description', 'inputSchema'])
            ->and($tool['name'])->toBe($name)
            ->and($tool['description'])->toBeString()
            ->and($tool['inputSchema'])->toHaveKey('type')
            ->and($tool['inputSchema']['type'])->toBe('object');
    }
});
