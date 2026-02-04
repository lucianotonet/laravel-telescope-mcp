<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\GatesTool;

test('gates tool lists no gate checks when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::GATE, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new GatesTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No gate checks found');
});

test('gates tool lists gate checks when repository returns entries', function () {
    $entry = new EntryResult(
        'gate-1',
        null,
        'batch-1',
        'gate',
        null,
        [
            'ability' => 'edit-post',
            'result' => true,
            'user' => 'user@example.com',
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::GATE, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new GatesTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Gate Checks');
    expect($response->content()->toArray()['text'] ?? '')->toContain('edit-post');
});

test('gates tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'gate-123',
        null,
        'batch-123',
        'gate',
        null,
        [
            'ability' => 'delete-post',
            'result' => false,
            'user' => 'user@example.com',
            'arguments' => ['post_id' => 123],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('gate-123')->once()->andReturn($entry);

    $tool = new GatesTool();
    $response = $tool->handle(new Request(['id' => 'gate-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Gate Check Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('delete-post');
});

test('gates tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new GatesTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('gates tool filters by ability', function () {
    $entry = new EntryResult(
        'gate-1',
        null,
        'batch-1',
        'gate',
        null,
        [
            'ability' => 'view-post',
            'result' => true,
            'user' => 'user@example.com',
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::GATE, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new GatesTool();
    $response = $tool->handle(new Request(['ability' => 'view-post']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('view-post');
});

test('gates tool shows denied status correctly', function () {
    $entry = new EntryResult(
        'gate-1',
        null,
        'batch-1',
        'gate',
        null,
        [
            'ability' => 'admin-access',
            'result' => false,
            'user' => 'user@example.com',
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::GATE, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new GatesTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Denied');
});
