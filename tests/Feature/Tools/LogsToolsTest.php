<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\LogsTool;

test('logs tool lists no logs when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::LOG, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([]));

    $tool = new LogsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('Log Entries');
    expect($response->content()->toArray()['text'] ?? '')->toContain('"total": 0');
});

test('logs tool lists logs when repository returns entries', function () {
    $entry = new EntryResult(
        'log-1',
        null,
        'batch-1',
        'log',
        null,
        ['level' => 'info', 'message' => 'Test message'],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::LOG, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new LogsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Log Entries');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Test message');
});

test('logs tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'log-123',
        null,
        'batch-1',
        'log',
        null,
        ['level' => 'error', 'message' => 'Error detail', 'context' => []],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('log-123')->once()->andReturn($entry);

    $tool = new LogsTool();
    $response = $tool->handle(new Request(['id' => 'log-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Log Entry Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Error detail');
});

test('logs tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('missing')->once()->andThrow(new \Exception('Not found'));

    $tool = new LogsTool();
    $response = $tool->handle(new Request(['id' => 'missing']), $repository);

    expect($response->isError())->toBeTrue();
});
