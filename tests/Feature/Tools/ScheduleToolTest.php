<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\ScheduleTool;

test('schedule tool lists no scheduled tasks when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::SCHEDULED_TASK, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new ScheduleTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No scheduled tasks found');
});

test('schedule tool lists scheduled tasks when repository returns entries', function () {
    $entry = new EntryResult(
        'schedule-1',
        null,
        'batch-1',
        'scheduled_task',
        null,
        [
            'command' => 'test:command',
            'expression' => '0 * * * *',
            'description' => 'Hourly test command',
            'exit_code' => 0,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::SCHEDULED_TASK, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new ScheduleTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Scheduled Tasks');
    expect($response->content()->toArray()['text'] ?? '')->toContain('test:command');
});

test('schedule tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'schedule-123',
        null,
        'batch-123',
        'scheduled_task',
        null,
        [
            'command' => 'detail:command',
            'expression' => '*/5 * * * *',
            'description' => 'Every 5 minutes',
            'exit_code' => 0,
            'output' => 'Command executed successfully',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('schedule-123')->once()->andReturn($entry);

    $tool = new ScheduleTool();
    $response = $tool->handle(new Request(['id' => 'schedule-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Scheduled Task Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('detail:command');
});

test('schedule tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new ScheduleTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('schedule tool shows failed status for failed tasks', function () {
    $entry = new EntryResult(
        'schedule-1',
        null,
        'batch-1',
        'scheduled_task',
        null,
        [
            'command' => 'failed:command',
            'expression' => '0 0 * * *',
            'description' => 'Daily failed command',
            'exit_code' => 1,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::SCHEDULED_TASK, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new ScheduleTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Failed');
});

test('schedule tool shows running status for tasks without exit code', function () {
    $entry = new EntryResult(
        'schedule-1',
        null,
        'batch-1',
        'scheduled_task',
        null,
        [
            'command' => 'running:command',
            'expression' => '0 * * * *',
            'description' => 'Running command',
            'exit_code' => null,
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::SCHEDULED_TASK, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new ScheduleTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Running');
});
