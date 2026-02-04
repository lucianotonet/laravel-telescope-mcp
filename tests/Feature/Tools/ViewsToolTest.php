<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\ViewsTool;

test('views tool lists no views when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::VIEW, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new ViewsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No view renderings found');
});

test('views tool lists views when repository returns entries', function () {
    $entry = new EntryResult(
        'view-1',
        null,
        'batch-1',
        'view',
        null,
        [
            'name' => 'welcome',
            'path' => 'resources/views/welcome.blade.php',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::VIEW, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new ViewsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('View Renderings');
    expect($response->content()->toArray()['text'] ?? '')->toContain('welcome');
});

test('views tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'view-123',
        null,
        'batch-123',
        'view',
        null,
        [
            'name' => 'detail',
            'path' => 'resources/views/detail.blade.php',
            'data' => ['title' => 'Detail Page', 'user' => 'John'],
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('view-123')->once()->andReturn($entry);

    $tool = new ViewsTool();
    $response = $tool->handle(new Request(['id' => 'view-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('View Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('detail');
});

test('views tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new ViewsTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('views tool lists views for request when request_id is provided', function () {
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
        ->with('type', 'view')
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

    $tool = new ViewsTool();
    $response = $tool->handle(new Request(['request_id' => 'req-123']), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No views found for request');
});
