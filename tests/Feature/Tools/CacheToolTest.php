<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\CacheTool;

test('cache tool lists no cache operations when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::CACHE, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new CacheTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No cache operations found');
});

test('cache tool lists cache operations when repository returns entries', function () {
    $entry = new EntryResult(
        'cache-1',
        null,
        'batch-1',
        'cache',
        null,
        [
            'type' => 'hit',
            'key' => 'test-key',
            'duration' => 0.5,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::CACHE, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new CacheTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Cache Operations');
    expect($response->content()->toArray()['text'] ?? '')->toContain('test-key');
});

test('cache tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'cache-123',
        null,
        'batch-123',
        'cache',
        null,
        [
            'type' => 'set',
            'key' => 'detail-key',
            'duration' => 1.2,
            'value' => 'test value',
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('cache-123')->once()->andReturn($entry);

    $tool = new CacheTool();
    $response = $tool->handle(new Request(['id' => 'cache-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Cache Operation Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('detail-key');
});

test('cache tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new CacheTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('cache tool filters by operation type', function () {
    $entry = new EntryResult(
        'cache-1',
        null,
        'batch-1',
        'cache',
        null,
        [
            'type' => 'miss',
            'key' => 'miss-key',
            'duration' => 0.3,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::CACHE, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new CacheTool();
    $response = $tool->handle(new Request(['operation' => 'miss']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('miss-key');
});

test('cache tool lists cache for request when request_id is provided', function () {
    $repository = Mockery::mock(EntriesRepository::class);

    // Mock DB for getBatchIdForEntry
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();

    // First call: getBatchIdForEntry
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('uuid', 'req-123')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('first')
        ->once()
        ->andReturn((object) ['batch_id' => 'batch-123']);

    // Second call: getEntriesByBatchId
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('batch_id', 'batch-123')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('type', 'cache')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('orderBy')
        ->with('sequence', 'asc')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('limit')
        ->with(50)
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('get')
        ->once()
        ->andReturn(collect([]));

    $tool = new CacheTool();
    $response = $tool->handle(new Request(['request_id' => 'req-123']), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No cache operations found for request');
});
