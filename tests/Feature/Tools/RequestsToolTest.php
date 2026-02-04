<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\RequestsTool;

test('requests tool lists no requests when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::REQUEST, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new RequestsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No requests found');
});

test('requests tool lists requests when repository returns entries', function () {
    $entry = new EntryResult(
        'id-1',
        null,
        'batch-1',
        'request',
        null,
        [
            'method' => 'GET',
            'uri' => '/api/test',
            'response_status' => 200,
            'duration' => 10,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::REQUEST, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new RequestsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('HTTP Requests');
    expect($response->content()->toArray()['text'] ?? '')->toContain('/api/test');
});

test('requests tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'req-123',
        null,
        'batch-1',
        'request',
        null,
        [
            'method' => 'GET',
            'uri' => '/detail',
            'response_status' => 200,
            'duration' => 5,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('req-123')->once()->andReturn($entry);

    $tool = new RequestsTool();
    $response = $tool->handle(new Request(['id' => 'req-123', 'include_related' => false]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('HTTP Request Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('/detail');
});

test('requests tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new RequestsTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('requests tool respects limit parameter', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::REQUEST, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([]));

    $tool = new RequestsTool();
    $response = $tool->handle(new Request(['limit' => 2]), $repository);

    $text = $response->content()->toArray()['text'] ?? '';
    expect($text)->toContain('HTTP Requests');
    expect($text)->toContain('"total": 0');
});
