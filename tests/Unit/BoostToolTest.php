<?php

namespace Tests\Unit;

use LucianoTonet\TelescopeMcp\BoostExtension\TelescopeBoostTool;
use LucianoTonet\TelescopeMcp\MCP\TelescopeMcpServer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tests\TestCase;

uses(TestCase::class);

class TelescopeHttpClientToolTestStub extends TelescopeBoostTool {
    public function description(): string { return ''; }
    public function schema(JsonSchema $schema): array { return []; }
    public function getToolNamePublic() { return $this->getToolNameFromClass(); }
}

class TelescopeExceptionsToolTestStub extends TelescopeBoostTool {
    public function description(): string { return ''; }
    public function schema(JsonSchema $schema): array { return []; }
    public function getToolNamePublic() { return $this->getToolNameFromClass(); }
}

test('getToolNameFromClass converts to kebab-case', function () {
    // Mock server to avoid constructor error
    $this->mock(TelescopeMcpServer::class);
    
    $httpTool = new TelescopeHttpClientToolTestStub();
    expect($httpTool->getToolNamePublic())->toBe('http-client-test-stub');
    
    $excTool = new TelescopeExceptionsToolTestStub();
    expect($excTool->getToolNamePublic())->toBe('exceptions-test-stub');
});

test('handle accepts array and returns Response', function () {
    $server = $this->mock(TelescopeMcpServer::class);
    $server->shouldReceive('executeTool')
        ->with('mocked-tool', ['foo' => 'bar'])
        ->once()
        ->andReturn(['result' => 'ok']);
        
    $tool = new class extends TelescopeBoostTool {
        public function description(): string { return ''; }
        public function schema(JsonSchema $schema): array { return []; }
        protected function getToolNameFromClass(): string { return 'mocked-tool'; }
    };
    
    $response = $tool->handle(['foo' => 'bar']);
    
    expect($response)->toBeInstanceOf(Response::class);
});

test('handle accepts Request and returns Response', function () {
    $server = $this->mock(TelescopeMcpServer::class);
    $server->shouldReceive('executeTool')
        ->with('mocked-tool', ['foo' => 'baz'])
        ->once()
        ->andReturn(['result' => 'ok']);
        
    $tool = new class extends TelescopeBoostTool {
        public function description(): string { return ''; }
        public function schema(JsonSchema $schema): array { return []; }
        protected function getToolNameFromClass(): string { return 'mocked-tool'; }
    };
    
    $request = new Request(['foo' => 'baz']);
    
    $response = $tool->handle($request);
    
    expect($response)->toBeInstanceOf(Response::class);
});
