<?php

beforeEach(function () {
    putenv('TELESCOPE_MCP_AUTH_ENABLED');
    putenv('TELESCOPE_MCP_BEARER_TOKEN');
    putenv('MCP_BEARER_TOKEN');
    putenv('TELESCOPE_MCP_MIDDLEWARE');
});

afterEach(function () {
    putenv('TELESCOPE_MCP_AUTH_ENABLED');
    putenv('TELESCOPE_MCP_BEARER_TOKEN');
    putenv('MCP_BEARER_TOKEN');
    putenv('TELESCOPE_MCP_MIDDLEWARE');
});

function mcpInitializePayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => [
                'name' => 'pest',
                'version' => '1.0.0',
            ],
        ],
    ];
}

test('mcp http route denies access when bearer auth is enabled and token is missing', function () {
    putenv('TELESCOPE_MCP_AUTH_ENABLED=true');
    putenv('TELESCOPE_MCP_BEARER_TOKEN=secret-token');
    $this->refreshApplication();

    $response = $this->postJson('/telescope-mcp', mcpInitializePayload());

    $response->assertStatus(401);
});

test('mcp http route allows access with valid authorization bearer token', function () {
    putenv('TELESCOPE_MCP_AUTH_ENABLED=true');
    putenv('TELESCOPE_MCP_BEARER_TOKEN=secret-token');
    $this->refreshApplication();

    $response = $this
        ->withHeaders(['Authorization' => 'Bearer secret-token'])
        ->postJson('/telescope-mcp', mcpInitializePayload());

    $response->assertOk();
    $response->assertJsonPath('result.serverInfo.name', 'Laravel Telescope MCP');
});

test('mcp http auth accepts token from MCP_BEARER_TOKEN env var', function () {
    putenv('TELESCOPE_MCP_AUTH_ENABLED');
    putenv('TELESCOPE_MCP_BEARER_TOKEN');
    putenv('MCP_BEARER_TOKEN=fallback-token');
    $this->refreshApplication();

    $response = $this
        ->withHeaders(['Authorization' => 'Bearer fallback-token'])
        ->postJson('/telescope-mcp', mcpInitializePayload());

    $response->assertOk();
});

test('middleware env parsing supports comma-separated values', function () {
    putenv('TELESCOPE_MCP_MIDDLEWARE=api,auth:sanctum');
    $this->refreshApplication();

    expect(config('telescope-mcp.middleware'))->toBe(['api', 'auth:sanctum']);
});
