<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\QueriesTool;

test('queries tool lists no queries when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::QUERY, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([]));

    $tool = new QueriesTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Database Queries');
    expect($response->content()->toArray()['text'] ?? '')->toContain('"total": 0');
});

test('queries tool lists queries when repository returns entries', function () {
    $entry = new EntryResult(
        'q-1',
        null,
        'batch-1',
        'query',
        null,
        [
            'sql' => 'select * from users',
            'time' => 5.2,
            'connection' => 'mysql',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::QUERY, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new QueriesTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Database Queries');
    expect($response->content()->toArray()['text'] ?? '')->toContain('select * from users');
});

test('queries tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'q-123',
        null,
        'batch-1',
        'query',
        null,
        [
            'sql' => 'SELECT * FROM posts',
            'time' => 10,
            'connection' => 'mysql',
            'bindings' => [],
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('q-123')->once()->andReturn($entry);

    $tool = new QueriesTool();
    $response = $tool->handle(new Request(['id' => 'q-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Database Query Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('SELECT * FROM posts');
});

test('queries tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('missing')->once()->andThrow(new \Exception('Not found'));

    $tool = new QueriesTool();
    $response = $tool->handle(new Request(['id' => 'missing']), $repository);

    expect($response->isError())->toBeTrue();
});

test('queries tool slow filter supports time field', function () {
    $entries = collect([
        new EntryResult('q-fast', null, 'batch-1', 'query', null, [
            'sql' => 'select 1',
            'time' => 10,
            'connection' => 'mysql',
            'created_at' => now()->toIso8601String(),
        ], now(), []),
        new EntryResult('q-slow', null, 'batch-1', 'query', null, [
            'sql' => 'select sleep(1)',
            'time' => 250,
            'connection' => 'mysql',
            'created_at' => now()->toIso8601String(),
        ], now(), []),
    ]);

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::QUERY, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn($entries);

    $tool = new QueriesTool();
    $response = $tool->handle(new Request(['slow' => true]), $repository);

    $text = $response->content()->toArray()['text'] ?? '';
    expect($text)->toContain('q-slow');
    expect($text)->not->toContain('q-fast');
});

test('queries tool details uses duration when available', function () {
    $entry = new EntryResult(
        'q-duration',
        null,
        'batch-1',
        'query',
        null,
        [
            'sql' => 'SELECT 1',
            'duration' => 150,
            'time' => 10,
            'connection' => 'mysql',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('q-duration')->once()->andReturn($entry);

    $tool = new QueriesTool();
    $response = $tool->handle(new Request(['id' => 'q-duration']), $repository);

    $text = $response->content()->toArray()['text'] ?? '';
    expect($text)->toContain('Duration: 150.00ms');
    expect($text)->toContain('"duration": 150');
});
