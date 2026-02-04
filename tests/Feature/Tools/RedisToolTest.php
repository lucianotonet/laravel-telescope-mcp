<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\RedisTool;

test('redis tool lists no redis operations when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::REDIS, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new RedisTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No Redis operations found');
});

test('redis tool lists redis operations when repository returns entries', function () {
    $entry = new EntryResult(
        'redis-1',
        null,
        'batch-1',
        'redis',
        null,
        [
            'command' => 'GET',
            'parameters' => ['key:123'],
            'duration' => 0.5,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::REDIS, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new RedisTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Redis Operations');
    expect($response->content()->toArray()['text'] ?? '')->toContain('GET');
});

test('redis tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'redis-123',
        null,
        'batch-123',
        'redis',
        null,
        [
            'command' => 'SET',
            'parameters' => ['key:456', 'value'],
            'duration' => 1.2,
            'connection' => 'default',
            'result' => 'OK',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('redis-123')->once()->andReturn($entry);

    $tool = new RedisTool();
    $response = $tool->handle(new Request(['id' => 'redis-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Redis Operation Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('SET');
});

test('redis tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new RedisTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('redis tool filters by command', function () {
    $entry = new EntryResult(
        'redis-1',
        null,
        'batch-1',
        'redis',
        null,
        [
            'command' => 'DEL',
            'parameters' => ['key:789'],
            'duration' => 0.3,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::REDIS, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new RedisTool();
    $response = $tool->handle(new Request(['command' => 'DEL']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('DEL');
});
