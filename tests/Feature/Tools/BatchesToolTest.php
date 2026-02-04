<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\BatchesTool;

test('batches tool lists no batches when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::BATCH, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new BatchesTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No batch operations found');
});

test('batches tool lists batches when repository returns entries', function () {
    $entry = new EntryResult(
        'batch-1',
        null,
        'batch-1',
        'batch',
        null,
        [
            'name' => 'Test Batch',
            'status' => 'finished',
            'totalJobs' => 10,
            'pendingJobs' => 0,
            'failedJobs' => 0,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::BATCH, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new BatchesTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Batch Operations');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Test Batch');
});

test('batches tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'batch-123',
        null,
        'batch-123',
        'batch',
        null,
        [
            'name' => 'Detail Batch',
            'status' => 'finished',
            'totalJobs' => 5,
            'pendingJobs' => 0,
            'failedJobs' => 0,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('batch-123')->once()->andReturn($entry);

    $tool = new BatchesTool();
    $response = $tool->handle(new Request(['id' => 'batch-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Batch Operation Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Detail Batch');
});

test('batches tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new BatchesTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('batches tool respects limit parameter', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::BATCH, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new BatchesTool();
    $response = $tool->handle(new Request(['limit' => 10]), $repository);

    $text = $response->content()->toArray()['text'] ?? '';
    expect($text)->toContain('No batch operations found');
});

test('batches tool filters by status', function () {
    $entry = new EntryResult(
        'batch-1',
        null,
        'batch-1',
        'batch',
        null,
        [
            'name' => 'Failed Batch',
            'status' => 'failed',
            'totalJobs' => 5,
            'pendingJobs' => 0,
            'failedJobs' => 2,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::BATCH, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new BatchesTool();
    $response = $tool->handle(new Request(['status' => 'failed']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Failed Batch');
});
