<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\DumpsTool;

test('dumps tool lists no dumps when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::DUMP, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new DumpsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No dump entries found');
});

test('dumps tool lists dumps when repository returns entries', function () {
    $entry = new EntryResult(
        'dump-1',
        null,
        'batch-1',
        'dump',
        null,
        [
            'file' => '/app/Test.php',
            'line' => 42,
            'dump' => 'Test dump value',
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::DUMP, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new DumpsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Dump Entries');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Test.php');
});

test('dumps tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'dump-123',
        null,
        'batch-123',
        'dump',
        null,
        [
            'file' => '/app/Detail.php',
            'line' => 100,
            'dump' => ['key' => 'value', 'nested' => ['data' => 123]],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('dump-123')->once()->andReturn($entry);

    $tool = new DumpsTool();
    $response = $tool->handle(new Request(['id' => 'dump-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Dump Entry Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Detail.php');
});

test('dumps tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new DumpsTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('dumps tool filters by file', function () {
    $entry = new EntryResult(
        'dump-1',
        null,
        'batch-1',
        'dump',
        null,
        [
            'file' => '/app/Filtered.php',
            'line' => 50,
            'dump' => 'Filtered dump',
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::DUMP, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new DumpsTool();
    $response = $tool->handle(new Request(['file' => '/app/Filtered.php']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Filtered.php');
});

test('dumps tool handles array dumps correctly', function () {
    $entry = new EntryResult(
        'dump-1',
        null,
        'batch-1',
        'dump',
        null,
        [
            'file' => '/app/Array.php',
            'line' => 10,
            'dump' => ['array', 'data'],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::DUMP, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new DumpsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Dump Entries');
});
