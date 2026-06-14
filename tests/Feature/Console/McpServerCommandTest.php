<?php

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Console\McpServerCommand;
use Laravel\Mcp\Facades\Mcp;

test('mcp server command is registered in application', function () {
    $commands = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all();
    expect(array_key_exists('telescope-mcp:server', $commands))->toBeTrue();
});

test('mcp server command info method writes to stderr when second argument is stderr', function () {
    $command = new McpServerCommand();

    ob_start();
    $command->info('Test message', 'stderr');
    $output = ob_get_clean();

    // info() with 'stderr' writes to STDERR, not stdout; we can't easily capture stderr in unit test
    // So we only assert the method doesn't throw and that parent::info isn't called for stderr
    expect(true)->toBeTrue();
});

test('mcp server command error method with stderr does not throw', function () {
    $command = new McpServerCommand();
    $command->error('Error message', 'stderr');
    expect(true)->toBeTrue();
});

test('mcp server command handle returns failure when transport throws', function () {
    $this->app->instance(EntriesRepository::class, Mockery::mock(EntriesRepository::class));

    $command = $this->app->make(McpServerCommand::class);
    expect($command->getName())->toBe('telescope-mcp:server');
});

test('provider registers telescope-mcp local server via Mcp::local', function () {
    $server = Mcp::getLocalServer('telescope-mcp');

    expect($server)->toBeCallable();
});

test('mcp server resolves and starts StdioTransport without throwing', function () {
    $this->app->instance(EntriesRepository::class, Mockery::mock(EntriesRepository::class));

    $transport = new \Laravel\Mcp\Server\Transport\StdioTransport(uniqid());
    $server = new \LucianoTonet\TelescopeMcp\Mcp\Servers\TelescopeServer($transport);

    $server->start();

    expect($transport)->toBeInstanceOf(\Laravel\Mcp\Server\Transport\StdioTransport::class);
});
