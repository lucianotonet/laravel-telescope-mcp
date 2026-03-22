<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\JobsTool;

test('jobs tool lists no jobs when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::JOB, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([]));

    $tool = new JobsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Jobs');
    expect($response->content()->toArray()['text'] ?? '')->toContain('"total": 0');
});

test('jobs tool lists jobs when repository returns entries', function () {
    $entry = new EntryResult(
        'job-1',
        null,
        'batch-1',
        'job',
        null,
        [
            'name' => 'App\Jobs\ProcessOrder',
            'status' => 'processed',
            'queue' => 'default',
            'attempts' => 1,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::JOB, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new JobsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Jobs');
    expect($response->content()->toArray()['text'] ?? '')->toContain('ProcessOrder');
});

test('jobs tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'job-123',
        null,
        'batch-1',
        'job',
        null,
        [
            'name' => 'App\Jobs\SendEmail',
            'status' => 'processed',
            'queue' => 'emails',
            'attempts' => 2,
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('job-123')->once()->andReturn($entry);

    $tool = new JobsTool();
    $response = $tool->handle(new Request(['id' => 'job-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Job Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('SendEmail');
});

test('jobs tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('missing')->once()->andThrow(new \Exception('Not found'));

    $tool = new JobsTool();
    $response = $tool->handle(new Request(['id' => 'missing']), $repository);

    expect($response->isError())->toBeTrue();
});

test('jobs tool details handle array payloads without string conversion errors', function () {
    $entry = new EntryResult(
        'job-array',
        null,
        'batch-1',
        'job',
        null,
        [
            'name' => 'App\Jobs\SyncUsers',
            'status' => 'failed',
            'queue' => 'imports',
            'attempts' => 3,
            'data' => ['tenant' => 'acme', 'ids' => [1, 2]],
            'exception' => ['message' => 'Boom', 'code' => 500],
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('job-array')->once()->andReturn($entry);

    $tool = new JobsTool();
    $response = $tool->handle(new Request(['id' => 'job-array']), $repository);

    $text = $response->content()->toArray()['text'] ?? '';
    expect($text)->toContain('"tenant": "acme"');
    expect($text)->toContain('"message": "Boom"');
    expect($response->isError())->toBeFalse();
});
