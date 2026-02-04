<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\EventsTool;

test('events tool lists no events when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::EVENT, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new EventsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No events found');
});

test('events tool lists events when repository returns entries', function () {
    $entry = new EntryResult(
        'event-1',
        null,
        'batch-1',
        'event',
        null,
        [
            'name' => 'TestEvent',
            'listeners' => ['Listener1', 'Listener2'],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::EVENT, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new EventsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Events');
    expect($response->content()->toArray()['text'] ?? '')->toContain('TestEvent');
});

test('events tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'event-123',
        null,
        'batch-123',
        'event',
        null,
        [
            'name' => 'DetailEvent',
            'payload' => ['key' => 'value'],
            'listeners' => ['Listener1'],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('event-123')->once()->andReturn($entry);

    $tool = new EventsTool();
    $response = $tool->handle(new Request(['id' => 'event-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Event Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('DetailEvent');
});

test('events tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new EventsTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('events tool filters by event name', function () {
    $entry = new EntryResult(
        'event-1',
        null,
        'batch-1',
        'event',
        null,
        [
            'name' => 'UserCreated',
            'listeners' => [],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::EVENT, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new EventsTool();
    $response = $tool->handle(new Request(['name' => 'UserCreated']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('UserCreated');
});

test('events tool lists events for request when request_id is provided', function () {
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
        ->with('type', 'event')
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

    $tool = new EventsTool();
    $response = $tool->handle(new Request(['request_id' => 'req-123']), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No events found for request');
});
