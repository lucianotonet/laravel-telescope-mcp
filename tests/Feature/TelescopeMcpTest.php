<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Telescope\TelescopeServiceProvider;
use Orchestra\Testbench\TestCase;
use LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider;

class TelescopeMcpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../../vendor/laravel/telescope/database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            TelescopeServiceProvider::class,
            TelescopeMcpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('telescope.enabled', true);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Configure MCP
        $app['config']->set('telescope-mcp.enabled', true);
        $app['config']->set('telescope-mcp.path', 'mcp');
        $app['config']->set('telescope-mcp.middleware', []);
        
        // Configure logging
        $app['config']->set('telescope-mcp.logging.enabled', true);
        $app['config']->set('telescope-mcp.logging.path', storage_path('logs/telescope-mcp.log'));
        $app['config']->set('telescope-mcp.logging.level', 'debug');
        
        // Configure error reporting
        $app['config']->set('app.debug', true);
    }

    /** @test */
    public function it_can_retrieve_telescope_logs()
    {
        \Log::info('Test log message');

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'mcp_Laravel_Telescope_MCP_logs',
                'arguments' => [
                    'limit' => 10
                ]
            ],
            'id' => 1
        ]);

        $this->assertNotNull($response);
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());

        // Debug response content
        dump('Response Content:', $response->content());
        dump('Response Status:', $response->status());
        dump('Response Headers:', $response->headers->all());

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'jsonrpc',
                    'result' => [
                        'content' => [
                            '*' => [
                                'type',
                                'text'
                            ]
                        ]
                    ],
                    'id'
                ]);
    }

    /** @test */
    public function it_can_retrieve_telescope_requests()
    {
        $this->get('/test-route');

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'mcp_Laravel_Telescope_MCP_requests',
                'arguments' => [
                    'limit' => 10
                ]
            ],
            'id' => 2
        ]);

        $this->assertNotNull($response);
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());

        // Debug response content
        dump('Response Content:', $response->content());
        dump('Response Status:', $response->status());
        dump('Response Headers:', $response->headers->all());

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'jsonrpc',
                    'result' => [
                        'content' => [
                            '*' => [
                                'type',
                                'text'
                            ]
                        ]
                    ],
                    'id'
                ]);
    }

    /** @test */
    public function it_can_get_manifest()
    {
        $response = $this->get('/mcp/manifest.json');

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
                    ]
                ]);
    }
} 