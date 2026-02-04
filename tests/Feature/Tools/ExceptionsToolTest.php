<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\ExceptionsTool;

test('exceptions tool lists no exceptions when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::EXCEPTION, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([]));

    $tool = new ExceptionsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Application Exceptions');
    expect($response->content()->toArray()['text'] ?? '')->toContain('"total": 0');
});

test('exceptions tool lists exceptions when repository returns entries', function () {
    $entry = new EntryResult(
        'ex-1',
        null,
        'batch-1',
        'exception',
        null,
        [
            'class' => \RuntimeException::class,
            'message' => 'Something broke',
            'file' => '/app/Test.php',
            'line' => 42,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::EXCEPTION, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new ExceptionsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Application Exceptions');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Something broke');
});

test('exceptions tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'ex-123',
        null,
        'batch-1',
        'exception',
        null,
        [
            'class' => \InvalidArgumentException::class,
            'message' => 'Invalid argument',
            'file' => '/app/Foo.php',
            'line' => 10,
            'trace' => [],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('ex-123')->once()->andReturn($entry);

    $tool = new ExceptionsTool();
    $response = $tool->handle(new Request(['id' => 'ex-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Exception Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Invalid argument');
});

test('exceptions tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('missing')->once()->andThrow(new \Exception('Not found'));

    $tool = new ExceptionsTool();
    $response = $tool->handle(new Request(['id' => 'missing']), $repository);

    expect($response->isError())->toBeTrue();
});
