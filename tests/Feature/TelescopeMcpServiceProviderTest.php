<?php

use Illuminate\Support\Facades\Route;

test('telescope mcp config is merged', function () {
    expect(config('telescope-mcp.path'))->toBe('telescope-mcp');
    expect(config('telescope-mcp.enabled'))->toBeTrue();
    expect(config('telescope-mcp.middleware'))->toBeArray();
});

test('telescope mcp test route is registered and returns json', function () {
    $response = $this->get('/telescope-mcp-test');

    $response->assertOk();
    $response->assertJson(['message' => 'Teste do Telescope MCP']);
});

test('telescope mcp commands are registered', function () {
    $this->artisan('telescope:mcp-connect', ['--help'])->assertSuccessful();
    $this->artisan('telescope-mcp:install', ['--help'])->assertSuccessful();
    $this->artisan('telescope-mcp:server', ['--help'])->assertSuccessful();
});

test('telescope mcp config can be overridden', function () {
    $this->app['config']->set('telescope-mcp.path', 'custom-mcp-path');

    expect(config('telescope-mcp.path'))->toBe('custom-mcp-path');
});
