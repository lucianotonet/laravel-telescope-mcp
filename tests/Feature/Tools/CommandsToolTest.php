<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\CommandsTool;

test('commands tool lists no commands when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::COMMAND, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new CommandsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No command executions found');
});

test('commands tool lists commands when repository returns entries', function () {
    $entry = new EntryResult(
        'cmd-1',
        null,
        'batch-1',
        'command',
        null,
        [
            'command' => 'test:command',
            'exit_code' => 0,
            'arguments' => ['--option=value'],
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::COMMAND, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new CommandsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Command Executions');
    expect($response->content()->toArray()['text'] ?? '')->toContain('test:command');
});

test('commands tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'cmd-123',
        null,
        'batch-123',
        'command',
        null,
        [
            'command' => 'detail:command',
            'exit_code' => 0,
            'arguments' => ['arg1', 'arg2'],
            'options' => ['verbose' => true],
            'output' => 'Command output',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('cmd-123')->once()->andReturn($entry);

    $tool = new CommandsTool();
    $response = $tool->handle(new Request(['id' => 'cmd-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Command Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('detail:command');
});

test('commands tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new CommandsTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('commands tool filters by command name', function () {
    $entry = new EntryResult(
        'cmd-1',
        null,
        'batch-1',
        'command',
        null,
        [
            'command' => 'migrate:run',
            'exit_code' => 0,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::COMMAND, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new CommandsTool();
    $response = $tool->handle(new Request(['command' => 'migrate:run']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('migrate:run');
});

test('commands tool shows error status for failed commands', function () {
    $entry = new EntryResult(
        'cmd-1',
        null,
        'batch-1',
        'command',
        null,
        [
            'command' => 'failed:command',
            'exit_code' => 1,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::COMMAND, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new CommandsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Error');
});
