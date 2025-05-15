<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LucianoTonet\TelescopeMcp\Support\JsonRpcResponse;

class JsonRpcErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withHeaders([
            'Accept' => 'application/json',
            // Content-Type for this specific test will be set in the call method
            // to avoid issues with other tests that might rely on defaultHeaders differently
        ]);
    }

    public function test_it_returns_parse_error_for_invalid_json()
    {
        // Prepare server variables, ensuring Content-Type is set for raw content
        $server = $this->transformHeadersToServerVars($this->defaultHeaders);
        $server['CONTENT_TYPE'] = 'application/json';

        $response = $this->call(
            'POST',
            config('telescope-mcp.path'), // Use the configured path
            [], // parameters
            [], // cookies
            [], // files
            $server, // server variables including headers
            'invalid json' // raw content
        );

        $response->assertStatus(400)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => JsonRpcResponse::PARSE_ERROR,
                    // 'message' => 'Parse error: Invalid JSON' // Mensagem pode variar
                ],
                'id' => null
            ])
            ->assertJsonPath('error.message', function (string $message) {
                return str_starts_with($message, 'Parse error: Invalid JSON in request body. Error:');
            });
    }

    public function test_it_returns_invalid_request_error_for_wrong_version()
    {
        $this->withHeaders(['Content-Type' => 'application/json']); // Ensure header for this request
        $response = $this->postJson(config('telescope-mcp.path'), [
            'jsonrpc' => '1.0',
            'method' => 'tools/call',
            'params' => [],
            'id' => 1
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => JsonRpcResponse::INVALID_REQUEST,
                    'message' => "Invalid JSON-RPC version. Must be '2.0'."
                ],
                'id' => 1
            ]);
    }

    public function test_it_returns_method_not_found_error_for_invalid_method()
    {
        $this->withHeaders(['Content-Type' => 'application/json']); // Ensure header for this request
        $response = $this->postJson(config('telescope-mcp.path'), [
            'jsonrpc' => '2.0',
            'method' => 'invalid_method',
            'params' => [],
            'id' => 1
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => JsonRpcResponse::METHOD_NOT_FOUND,
                    'message' => 'Method not found: invalid_method'
                ],
                'id' => 1
            ]);
    }

    public function test_it_returns_invalid_params_error_for_missing_tool_name()
    {
        $this->withHeaders(['Content-Type' => 'application/json']); // Ensure header for this request
        $response = $this->postJson(config('telescope-mcp.path'), [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [], // This should trigger invalid params for missing tool name
            'id' => 1
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => JsonRpcResponse::INVALID_PARAMS,
                    'message' => 'Invalid params: tool name is required and must be a string.'
                ],
                'id' => 1
            ]);
    }

    public function test_it_returns_internal_error_for_nonexistent_tool()
    {
        $this->withHeaders(['Content-Type' => 'application/json']); // Ensure header for this request
        $response = $this->postJson(config('telescope-mcp.path'), [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'nonexistent_tool',
                'arguments' => []
            ],
            'id' => 1
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => JsonRpcResponse::INTERNAL_ERROR,
                    'message' => 'Tool not found: nonexistent_tool'
                ],
                'id' => 1
            ]);
    }

    public function test_it_returns_success_response_for_valid_request()
    {
        $this->withHeaders(['Content-Type' => 'application/json']); // Ensure header for this request
        $response = $this->postJson(config('telescope-mcp.path'), [
            'jsonrpc' => '2.0',
            'method' => 'mcp.manifest',
            'params' => [],
            'id' => 1
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result' => [
                    'protocolVersion',
                    'serverInfo' => [
                        'name',
                        'version',
                        'description'
                    ],
                    'capabilities' => [
                        'tools'
                    ]
                ],
                'id'
            ])
            ->assertJson([
                'jsonrpc' => '2.0',
                'id' => 1
            ]);
    }
} 