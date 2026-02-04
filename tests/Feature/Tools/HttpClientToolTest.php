<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\HttpClientTool;

test('http client tool lists no requests when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::CLIENT_REQUEST, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new HttpClientTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No HTTP client requests found');
});

test('http client tool lists requests when repository returns entries', function () {
    $entry = new EntryResult(
        'http-1',
        null,
        'batch-1',
        'client_request',
        null,
        [
            'method' => 'GET',
            'uri' => 'https://api.example.com/test',
            'response_status' => 200,
            'duration' => 150,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::CLIENT_REQUEST, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new HttpClientTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('HTTP Client Requests');
    expect($response->content()->toArray()['text'] ?? '')->toContain('api.example.com');
});

test('http client tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'http-123',
        null,
        'batch-123',
        'client_request',
        null,
        [
            'method' => 'POST',
            'uri' => 'https://api.example.com/create',
            'response_status' => 201,
            'duration' => 250,
            'headers' => ['Content-Type' => 'application/json'],
            'payload' => ['name' => 'Test'],
            'response' => ['id' => 123],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('http-123')->once()->andReturn($entry);

    $tool = new HttpClientTool();
    $response = $tool->handle(new Request(['id' => 'http-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('HTTP Client Request Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('POST');
});

test('http client tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new HttpClientTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('http client tool filters by method', function () {
    $entry = new EntryResult(
        'http-1',
        null,
        'batch-1',
        'client_request',
        null,
        [
            'method' => 'DELETE',
            'uri' => 'https://api.example.com/delete',
            'response_status' => 204,
            'duration' => 100,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::CLIENT_REQUEST, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new HttpClientTool();
    $response = $tool->handle(new Request(['method' => 'DELETE']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('DELETE');
});

test('http client tool filters by status code', function () {
    $entry = new EntryResult(
        'http-1',
        null,
        'batch-1',
        'client_request',
        null,
        [
            'method' => 'GET',
            'uri' => 'https://api.example.com/error',
            'response_status' => 404,
            'duration' => 50,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::CLIENT_REQUEST, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new HttpClientTool();
    $response = $tool->handle(new Request(['status' => 404]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('404');
});
